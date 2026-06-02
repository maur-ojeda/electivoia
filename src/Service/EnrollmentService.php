<?php

namespace App\Service;

use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\User;
use App\Repository\CourseRepository;
use App\Repository\EnrollmentRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class EnrollmentService
{
    private $entityManager;
    private $courseRepository;
    private $enrollmentRepository;
    private $userRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        CourseRepository $courseRepository,
        EnrollmentRepository $enrollmentRepository,
        UserRepository $userRepository
    ) {
        $this->entityManager = $entityManager;
        $this->courseRepository = $courseRepository;
        $this->enrollmentRepository = $enrollmentRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * Returns available courses with real-time capacity and enrollment status per student.
     *
     * @param string|null $category Filter by category name
     * @param string|null $search   Search term for name or description
     * @return array{courses: array<int, array{id: int, name: string, category: string|null, teacher: string, schedule: string|null, capacity: string, spots_available: int, enrolled: bool}>, total: int}
     */
    public function getAvailableCourses(?string $category = null, ?string $search = null): array
    {
        $courses = $this->courseRepository->findAvailableForStudent(
            grade: null,
            category: $category,
            search: $search,
        );

        $result = [];
        foreach ($courses as $course) {
            $spotsAvailable = $course->getMaxCapacity() - $course->getCurrentEnrollment();
            $teacher = $course->getTeacher();
            $cat = $course->getCategory();

            $result[] = [
                'id' => $course->getId(),
                'name' => $course->getName(),
                'category' => $cat?->getName(),
                'teacher' => $teacher ? $teacher->getFullName() : '',
                'schedule' => $course->getSchedule(),
                'capacity' => $course->getCurrentEnrollment() . '/' . $course->getMaxCapacity(),
                'spots_available' => $spotsAvailable,
            ];
        }

        return [
            'courses' => $result,
            'total' => count($result),
        ];
    }

    /**
     * Returns full details for one course + enrollment status for a specific student.
     *
     * @return array{id: int, name: string, description: string|null, category: string|null, teacher: string, schedule: string|null, capacity: string, spots_available: int, enrolled: bool, can_enroll: bool, enrollment_message: string}
     */
    public function getCourseDetails(int $courseId, User $student): array
    {
        $course = $this->courseRepository->find($courseId);

        if ($course === null) {
            return [
                'id' => 0,
                'name' => '',
                'description' => null,
                'category' => null,
                'teacher' => '',
                'schedule' => null,
                'capacity' => '0/0',
                'spots_available' => 0,
                'enrolled' => false,
                'can_enroll' => false,
                'enrollment_message' => 'Curso no encontrado.',
            ];
        }

        $enrolled = $this->isEnrolled($student, $course);
        $spotsAvailable = $course->getMaxCapacity() - $course->getCurrentEnrollment();
        $teacher = $course->getTeacher();
        $cat = $course->getCategory();

        $canEnroll = false;
        $enrollmentMessage = '';

        if (!$course->isActive()) {
            $enrollmentMessage = 'El curso no está disponible.';
        } elseif ($enrolled) {
            $enrollmentMessage = 'Ya estás inscrito en este curso.';
        } elseif ($spotsAvailable <= 0) {
            $enrollmentMessage = 'No hay cupos disponibles.';
        } else {
            $canEnroll = true;
        }

        return [
            'id' => $course->getId(),
            'name' => $course->getName(),
            'description' => $course->getDescription(),
            'category' => $cat?->getName(),
            'teacher' => $teacher ? $teacher->getFullName() : '',
            'schedule' => $course->getSchedule(),
            'capacity' => $course->getCurrentEnrollment() . '/' . $course->getMaxCapacity(),
            'spots_available' => $spotsAvailable,
            'enrolled' => $enrolled,
            'can_enroll' => $canEnroll,
            'enrollment_message' => $enrollmentMessage,
        ];
    }

    /**
     * Checks if a student is enrolled in a specific course.
     */
    public function isEnrolled(User $student, Course $course): bool
    {
        $enrollment = $this->enrollmentRepository->findOneBy([
            'student' => $student,
            'course' => $course,
        ]);

        return $enrollment !== null;
    }

    public function enrollStudent(User $student, Course $course): array // Devuelve un array con resultado y mensaje
    {
        // Verificar que el usuario sea un estudiante (opcional, pero buena práctica aquí o en el controlador)
        // if (!in_array('ROLE_STUDENT', $student->getRoles())) {
        //     return ['success' => false, 'message' => 'El usuario no es un estudiante.'];
        // }

        // Verificar si el curso está activo
        if (!$course->isActive()) {
            return ['success' => false, 'message' => 'El curso no está disponible.'];
        }

        // Verificar si ya está inscrito
        $existingEnrollment = $this->enrollmentRepository->findOneBy(['student' => $student, 'course' => $course]);
        if ($existingEnrollment) {
            return ['success' => false, 'message' => 'Ya estás inscrito en este curso. existingEnrollment'];
        }

        $currentEnrollmentCount = $course->getCurrentEnrollment();
        $maxCapacity = $course->getMaxCapacity();

        if ($currentEnrollmentCount < $maxCapacity) {
            // Hay cupo, inscribir directamente
            $enrollment = new Enrollment();
            $enrollment->setStudent($student);
            $enrollment->setCourse($course);
            $enrollment->setEnrolledAt(new \DateTime()); // O el momento actual

            $this->entityManager->persist($enrollment);
            $course->setCurrentEnrollment($currentEnrollmentCount + 1);
            $this->entityManager->flush();

            return ['success' => true, 'message' => 'Inscripción exitosa.'];
        } else {
            // No hay cupo, aplicar regla de prioridad
            $averageGradeStudent = $student->getAverageGrade();

            if ($averageGradeStudent === null) {
                // Opcional: Si no tiene promedio, no puede inscribirse si está lleno
                // return ['success' => false, 'message' => 'No puedes inscribirte en un curso lleno sin un promedio de notas registrado.'];

                // Opcional: Si no tiene promedio, se le da la menor prioridad (no se inscribe)
                return ['success' => false, 'message' => 'No puedes inscribirte en un curso lleno sin un promedio de notas registrado.'];
            }

            // Buscar inscripciones en este curso ordenadas por promedio de forma ascendente (menor promedio primero)
            $enrollmentsInCourse = $this->enrollmentRepository->findEnrollmentsByCourseOrderedByGradeAsc($course); // Necesitas crear este método en el repositorio

            $displacedStudent = null;
            $displacedEnrollment = null;

            foreach ($enrollmentsInCourse as $enrollment) {
                $currentStudent = $enrollment->getStudent();
                $currentStudentGrade = $currentStudent->getAverageGrade();

                // Si el estudiante actual no tiene promedio o tiene uno menor, es candidato a ser desplazado
                if ($currentStudentGrade === null || $averageGradeStudent > $currentStudentGrade) {
                    $displacedStudent = $currentStudent;
                    $displacedEnrollment = $enrollment;
                    break; // El primero encontrado con menor promedio (o sin promedio) es el que se va
                }
            }

            if ($displacedStudent) {
                // Desplazar al estudiante con menor promedio
                $this->entityManager->remove($displacedEnrollment);

                // Crear nueva inscripción para el estudiante con mayor promedio
                $newEnrollment = new Enrollment();
                $newEnrollment->setStudent($student);
                $newEnrollment->setCourse($course);
                $newEnrollment->setEnrolledAt(new \DateTime());

                $this->entityManager->persist($newEnrollment);

                $this->entityManager->flush(); // Aplica los cambios (eliminar y crear)

                return [
                    'success' => true,
                    'message' => "Inscripción exitosa. Has reemplazado a {$displacedStudent->getUserIdentifier()} en el curso.",
                    'displaced' => $displacedStudent->getUserIdentifier() // O ID
                ];
            } else {
                // Nadie tiene menor promedio, no se puede inscribir
                return ['success' => false, 'message' => 'No hay cupo disponible y no tienes prioridad suficiente (promedio de notas).'];
            }
        }
    }
}
