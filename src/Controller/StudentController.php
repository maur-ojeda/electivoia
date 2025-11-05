<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\CourseCategory;
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
        $searchQuery = trim((string) $request->query->get('q', ''));
        $selectedCategory = $request->query->get('category');

        // Obtener todas las categorías para el filtro
        $allCategories = $em->getRepository(CourseCategory::class)->findAll();

        // Construir la consulta base
        $qb = $em->getRepository(Course::class)->createQueryBuilder('c')
            ->where('c.isActive = true')
            ->leftJoin('c.teacher', 't')
            ->addSelect('t');

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

        // Filtro por búsqueda (nombre o descripción)
        if ($searchQuery !== '') {
            $qb->andWhere('LOWER(c.name) LIKE :search OR LOWER(c.description) LIKE :search')
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
}
