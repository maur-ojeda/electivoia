<?php

namespace App\Controller;

use App\Entity\Attendance;
use App\Entity\Course;
use App\Entity\CourseCategory;
use App\Entity\Enrollment;
use App\Service\EnrollmentService;
use App\Entity\InterestProfile;
use App\Service\NotificationService;
use App\Service\RecommendationService;
use App\Service\TenantContext;
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
    public function __construct(
        private EnrollmentService $enrollmentService,
        private NotificationService $notificationService,
        private TenantContext $tenantContext
    ) {}

    //---------------

    #[Route('/dashboard', name: 'dashboard')]
    public function dashboard(EntityManagerInterface $em): Response
    {
        $student = $this->getUser();
        $enrollments = $em->getRepository(Enrollment::class)->findBy(['student' => $student]);

        $totalCourses = count($enrollments);
        $totalSessions = 0;
        $totalAttended = 0;
        $courseStats = [];

        foreach ($enrollments as $enrollment) {
            $course = $enrollment->getCourse();
            $records = $em->getRepository(Attendance::class)->findBy(['student' => $student, 'course' => $course]);
            $sessions = count($records);
            $attended = count(array_filter($records, fn($a) => in_array($a->getStatus(), ['present', 'justified'])));

            $totalSessions += $sessions;
            $totalAttended += $attended;

            $courseStats[] = [
                'course' => $course,
                'sessions' => $sessions,
                'attended' => $attended,
                'percentage' => $sessions > 0 ? round($attended / $sessions * 100) : null,
            ];
        }

        $avgAttendance = $totalSessions > 0 ? round($totalAttended / $totalSessions * 100) : null;

        return $this->render('student/dashboard.html.twig', [
            'student' => $student,
            'totalCourses' => $totalCourses,
            'avgAttendance' => $avgAttendance,
            'courseStats' => $courseStats,
        ]);
    }

    #[Route('/courses', name: 'courses')]
    public function courses(
        Request $request,
        EntityManagerInterface $em,
        RecommendationService $recommendationService
    ): Response {
        $student = $this->getUser();
        $showAvailableOnly = (bool) $request->query->get('available', false);
        $searchQuery = trim((string) $request->query->get('q', ''));
        $selectedCategory = $request->query->get('category');

        // Obtener todas las categorías para el filtro
        $allCategories = $em->getRepository(CourseCategory::class)->findAll();

        // Construir la consulta base
        $qb = $em->getRepository(Course::class)->createQueryBuilder('c')
            ->where('c.isActive = true')
            ->leftJoin('c.teacher', 't')
            ->addSelect('t');

        if ($this->tenantContext->hasSchool()) {
            $qb->andWhere('c.school = :_school')
                ->setParameter('_school', $this->tenantContext->getCurrentSchool());
        }

        // Filtro por disponibilidad
        if ($showAvailableOnly) {
            $qb
                ->leftJoin('c.enrollments', 'e')
                ->groupBy('c.id, t.id')
                ->having('c.maxCapacity > COUNT(e.id)');
        }

        // Filtro por categoría
        if ($selectedCategory) {
            $qb->andWhere('c.category = :categoryId')
                ->setParameter('categoryId', $selectedCategory);
        }

        // Filtro por búsqueda (nombre, descripción u horario)
        if ($searchQuery !== '') {
            $qb->andWhere('LOWER(c.name) LIKE :search OR LOWER(c.description) LIKE :search OR LOWER(c.schedule) LIKE :search')
                ->setParameter('search', '%' . strtolower($searchQuery) . '%');
        }

        $allCourses = $qb->getQuery()->getResult();

        // Filtrar por grado del estudiante
        $studentGrade = $student->getGrade();
        if ($studentGrade) {
            $courses = array_filter($allCourses, function (Course $course) use ($studentGrade) {
                $targetGrades = $course->getTargetGrades();
                return $targetGrades !== null && in_array($studentGrade, $targetGrades);
            });
            $courses = array_values($courses);
        } else {
            $courses = [];
        }

        // Contar inscripciones por curso (optimizado)
        $courseIds = array_map(fn(Course $c) => $c->getId(), $courses);
        $enrollmentCounts = [];
        if (!empty($courseIds)) {
            $counts = $em->createQuery('
            SELECT c.id, COUNT(e.id)
            FROM App\Entity\Course c
            LEFT JOIN c.enrollments e
            WHERE c.id IN (:ids)
            GROUP BY c.id
        ')->setParameter('ids', $courseIds)->getScalarResult();
            $enrollmentCounts = array_column($counts, 1, 0);
        }

        // Recomendaciones
        $recommendations = $recommendationService->getForStudentWithReasons($student);
        $enrolledCourseIds = array_map(
            fn(Enrollment $e) => $e->getCourse()->getId(),
            $em->getRepository(Enrollment::class)->findBy(['student' => $student])
        );

        $interests = $student->getInterestProfile()?->getInterests() ?? [];



        // Reagrupar enrollments por curso (como antes)
        $enrollmentsByCourse = [];
        foreach ($courses as $course) {
            // Doctrine ya trajo los enrollments gracias al fetch join
            $enrollmentsByCourse[$course->getId()] = $course->getEnrollments()->toArray();
        }


        $currentSchool = $this->tenantContext->getCurrentSchool();
        $enrollmentOpen = $currentSchool === null || $currentSchool->isEnrollmentOpen();

        return $this->render('student/courses.html.twig', [
            'student' => $student,
            'courses' => $courses,
            'enrollmentCounts' => $enrollmentCounts,
            'showAvailableOnly' => $showAvailableOnly,
            'recommendations' => $recommendations,
            'enrolledCourseIds' => $enrolledCourseIds,
            'interests' => $interests,
            'allCategories' => $allCategories,
            'selectedCategory' => $selectedCategory,
            'searchQuery' => $searchQuery,
            'enrollmentsByCourse' => $enrollmentsByCourse,
            'enrollmentOpen' => $enrollmentOpen,
            'school' => $currentSchool,
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

        // HU-16: Check school-wide enrollment period
        $school = $this->tenantContext->getCurrentSchool();
        if ($school !== null && !$school->isEnrollmentOpen()) {
            $this->addFlash('error', 'El período de inscripción del colegio está cerrado.');
            return $this->redirectToRoute('student_courses');
        }

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
            $this->notificationService->sendEnrollmentConfirmation($student, $course);
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
                    $this->notificationService->sendEnrollmentConfirmation($student, $course);
                    $this->notificationService->sendEnrollmentDisplaced($lowestStudent, $student, $course);
                    $this->addFlash('success', "¡Inscripción exitosa! Reemplazaste a {$lowestStudent->getFullName()}.");
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
            $interests = $request->request->all('interests') ?: [];
            $validCategories = [
                'Filosofía',
                'Historia, geografía y ciencias sociales',
                'Lengua y literatura',
                'Matemática',
                'Ciencias',
                'Artes',
                'Educación física y salud'
            ];

            $filteredInterests = [];
            foreach ($validCategories as $cat) {
                $filteredInterests[$cat] = (int) ($interests[$cat] ?? 0);
            }

            $profile->setInterests($filteredInterests);
            $em->persist($profile);
            $em->flush();
            $this->addFlash('success', 'Perfil de intereses actualizado.');
            return $this->redirectToRoute('student_courses');
        }

        return $this->render('student/profile.html.twig', [
            'profile' => $profile,
            'interests' => $profile->getInterests() ?? [],
        ]);
    }

    #[Route('/attendance', name: 'attendance')]
    public function attendance(EntityManagerInterface $em): Response
    {
        $student = $this->getUser();
        $enrollments = $em->getRepository(Enrollment::class)->findBy(['student' => $student]);

        $attendanceData = [];
        foreach ($enrollments as $enrollment) {
            $course = $enrollment->getCourse();
            $records = $em->getRepository(Attendance::class)->findBy(
                ['student' => $student, 'course' => $course],
                ['date' => 'DESC']
            );

            $total = count($records);
            $present = count(array_filter($records, fn($a) => $a->getStatus() === 'present'));
            $absent = count(array_filter($records, fn($a) => $a->getStatus() === 'absent'));
            $justified = count(array_filter($records, fn($a) => $a->getStatus() === 'justified'));
            $percentage = $total > 0 ? round(($present + $justified) / $total * 100) : null;

            $attendanceData[] = [
                'course' => $course,
                'records' => $records,
                'total' => $total,
                'present' => $present,
                'absent' => $absent,
                'justified' => $justified,
                'percentage' => $percentage,
            ];
        }

        return $this->render('student/attendance.html.twig', [
            'attendanceData' => $attendanceData,
        ]);
    }

    #[Route('/unenroll/{id}', name: 'unenroll', methods: ['POST'])]
    #[IsGranted('ROLE_STUDENT')]
    public function unenroll(Course $course, EntityManagerInterface $em): Response
    {
        $student = $this->getUser();

        // HU-16: Check school-wide enrollment period
        $school = $this->tenantContext->getCurrentSchool();
        if ($school !== null && !$school->isEnrollmentOpen()) {
            $this->addFlash('error', 'El período de inscripción del colegio está cerrado.');
            return $this->redirectToRoute('student_courses');
        }

        // HU-4: Check enrollment deadline
        if ($course->getEnrollmentDeadline() && new \DateTimeImmutable() > $course->getEnrollmentDeadline()) {
            $this->addFlash('error', 'La fecha límite de inscripción ya pasó. No es posible darse de baja.');
            return $this->redirectToRoute('student_courses');
        }

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
        return $this->redirectToRoute('student_courses');
    }
}
