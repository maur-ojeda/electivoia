<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Enrollment;
use App\Service\EnrollmentService;
use App\Entity\InterestProfile;
use App\Service\RecommendationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/student', name: 'student_')]
#[IsGranted('ROLE_STUDENT')]
class StudentController extends AbstractController
{
    private $enrollmentService;

    public function __construct(EnrollmentService $enrollmentService)
    {
        $this->enrollmentService = $enrollmentService;
    }

    //---------------

    #[Route('/courses', name: 'courses')]
    public function courses(
        Request $request,
        EntityManagerInterface $em,
        RecommendationService $recommendationService
    ): Response {
        $student = $this->getUser();
        $showAvailableOnly = (bool) $request->query->get('available', false);

        // Iniciar la consulta base
        $qb = $em->getRepository(Course::class)->createQueryBuilder('c')
            ->where('c.isActive = true');

        // --- FILTRAR POR DISPONIBILIDAD (si aplica) ---
        if ($showAvailableOnly) {
            $qb
                ->leftJoin('c.enrollments', 'e')
                ->leftJoin('c.teacher', 't')
                ->addSelect('t')
                ->groupBy('c.id')
                ->addGroupBy('t.id')
                ->having('c.maxCapacity > COUNT(e.id)');
        } else {
            // Si no se filtra por disponibilidad, solo seleccionamos c y t si se necesita t
            $qb->leftJoin('c.teacher', 't')
                ->addSelect('t');
        }
        // ------------------------------------------------

        // Ejecutar la consulta base (filtrada por isActive y disponibilidad)
        $allCourses = $qb->getQuery()->getResult();

        // Filtrar por grado del estudiante en PHP
        $studentGrade = $student->getGrade(); // Asumiendo que getGrade() devuelve un string como '1B', '2M', etc.
        if ($studentGrade) {
            $filteredCourses = array_filter($allCourses, function (Course $course) use ($studentGrade) {
                $targetGrades = $course->getTargetGrades(); // Devuelve el array PHP
                // Verificar si el grado del estudiante está en el array de grados objetivo
                // Asegurarse de que targetGrades no sea null antes de usar in_array
                return $targetGrades !== null && in_array($studentGrade, $targetGrades);
            });
            $courses = array_values($filteredCourses); // Reindexar el array si es necesario
        } else {
            // Si el estudiante no tiene grado, no debería ver ningún curso basado en este filtro
            $courses = [];
        }

        // Obtener inscripciones del estudiante
        $enrollments = $em->getRepository(Enrollment::class)->findBy(['student' => $student]);

        // Contar inscripciones por curso
        $enrollmentCounts = [];
        foreach ($courses as $course) {
            $enrollmentCounts[$course->getId()] = $em->getRepository(Enrollment::class)->count(['course' => $course]);
        }

        // --- Obtener recomendaciones ---
        // Usar el método correcto del servicio
        $recommendations = $recommendationService->getForStudentWithReasons($student);

        // También necesitas pasar la lista de IDs de cursos en los que el estudiante está inscrito
        // para mostrar correctamente el botón "Inscrito".
        $enrolledCourseIds = array_map(function (Enrollment $enrollment) {
            return $enrollment->getCourse()->getId();
        }, $enrollments);

        return $this->render('student/courses.html.twig', [
            'student' => $student,
            'courses' => $courses,
            'enrollments' => $enrollments,
            'enrollmentCounts' => $enrollmentCounts,
            'showAvailableOnly' => $showAvailableOnly,
            'recommendations' => $recommendations, // <-- Variable añadida
            'enrolledCourseIds' => $enrolledCourseIds, // <-- Variable añadida
        ]);
    }


    //---------------

    #[Route('/enrollments', name: 'enrollments')]
    public function enrollments(EntityManagerInterface $em): Response
    {
        $student = $this->getUser();
        $enrollments = $em->getRepository(Enrollment::class)->findBy(['student' => $student]);

        return $this->render('student/enrollments.html.twig', [
            'enrollments' => $enrollments,
        ]);
    }

    #[Route('/enroll/{id}', name: 'enroll', methods: ['POST'])]
    public function enroll(Course $course, EntityManagerInterface $em): Response
    {
        $student = $this->getUser();
        $now = new \DateTimeImmutable();

        if ($course->getEnrollmentDeadline() && $now > $course->getEnrollmentDeadline()) {
            $this->addFlash('error', 'La inscripción para este curso ya está cerrada.');
            return $this->redirectToRoute('student_courses');
        }

        $existing = $em->getRepository(Enrollment::class)->findOneBy([
            'student' => $student,
            'course' => $course
        ]);
        if ($existing) {
            $this->addFlash('warning', 'Ya estás inscrito en este curso.');
            return $this->redirectToRoute('student_courses');
        }

        $currentCount = $em->getRepository(Enrollment::class)->count(['course' => $course]);
        if ($currentCount < $course->getMaxCapacity()) {
            $enrollment = new Enrollment();
            $enrollment->setStudent($student);
            $enrollment->setCourse($course);
            $enrollment->setEnrolledAt(new \DateTime());
            $em->persist($enrollment);
            $em->flush();
            $this->addFlash('success', '¡Inscripción exitosa!');
        } else {
            $studentGrade = $student->getAverageGrade();
            if ($studentGrade === null) {
                $this->addFlash('error', 'No puedes inscribirte sin promedio registrado.');
                return $this->redirectToRoute('student_courses');
            }

            $lowestEnrollment = $em->createQuery('
                SELECT e FROM App\Entity\Enrollment e
                JOIN e.student s
                WHERE e.course = :course
                ORDER BY COALESCE(s.averageGrade, 0) ASC
            ')
                ->setParameter('course', $course)
                ->setMaxResults(1)
                ->getOneOrNullResult();

            if ($lowestEnrollment) {
                $lowestStudent = $lowestEnrollment->getStudent();
                $lowestGrade = $lowestStudent->getAverageGrade() ?? 0;
                if ($studentGrade > $lowestGrade) {
                    $em->remove($lowestEnrollment);
                    $newEnrollment = new Enrollment();
                    $newEnrollment->setStudent($student);
                    $newEnrollment->setCourse($course);
                    $newEnrollment->setEnrolledAt(new \DateTime());
                    $em->persist($newEnrollment);
                    $em->flush();
                    $this->addFlash('success', "¡Inscripción exitosa! Reemplazaste a {$lowestStudent->getEmail()}.");
                } else {
                    $this->addFlash('error', 'No hay cupo y tu promedio no es suficiente.');
                }
            } else {
                $this->addFlash('error', 'Error al verificar prioridad.');
            }
        }

        return $this->redirectToRoute('student_courses');
    }

    #[Route('/profile', name: 'profile')]
    public function profile(Request $request, EntityManagerInterface $em): Response
    {
        $student = $this->getUser();
        $profile = $student->getInterestProfile() ?? new InterestProfile();
        $profile->setStudent($student);

        if ($request->isMethod('POST')) {
            $interests = [
                'arte' => (int) $request->request->get('arte', 0),
                'ciencia' => (int) $request->request->get('ciencia', 0),
                'tecnologia' => (int) $request->request->get('tecnologia', 0),
                'deporte' => (int) $request->request->get('deporte', 0),
                'musica' => (int) $request->request->get('musica', 0),
            ];
            $profile->setInterests($interests);
            $em->persist($profile);
            $em->flush();
            $this->addFlash('success', 'Perfil actualizado. Recomendaciones actualizadas.');
            return $this->redirectToRoute('student_courses');
        }

        return $this->render('student/profile.html.twig', [
            'profile' => $profile,
        ]);
    }

    #[Route('/unenroll/{id}', name: 'unenroll', methods: ['POST'])]
    #[IsGranted('ROLE_STUDENT')]
    public function unenroll(Course $course, EntityManagerInterface $em): Response
    {
        $student = $this->getUser();
        $enrollment = $em->getRepository(Enrollment::class)->findOneBy([
            'student' => $student,
            'course' => $course
        ]);

        if (!$enrollment) {
            $this->addFlash('error', 'No estás inscrito en este curso.');
            return $this->redirectToRoute('student_courses');
        }

        $em->remove($enrollment);
        $em->flush();
        $this->addFlash('success', 'Te has dado de baja del curso.');
        return $this->redirectToRoute('student_enrollments');
    }

    #[Route('/update-interests', name: 'update_interests', methods: ['POST'])]
    #[IsGranted('ROLE_STUDENT')]
    public function updateInterests(Request $request, EntityManagerInterface $em): Response
    {
        $student = $this->getUser();
        $profile = $student->getInterestProfile() ?? new InterestProfile();
        $profile->setStudent($student);

        $interests = [
            'arte' => (int) $request->request->get('arte', 0),
            'ciencia' => (int) $request->request->get('ciencia', 0),
            'tecnologia' => (int) $request->request->get('tecnologia', 0),
            'deporte' => (int) $request->request->get('deporte', 0),
            'musica' => (int) $request->request->get('musica', 0),
        ];
        $profile->setInterests($interests);
        $em->persist($profile);
        $em->flush();

        return $this->redirectToRoute('student_courses');
    }
}
