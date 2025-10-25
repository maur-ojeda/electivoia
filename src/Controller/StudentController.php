<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Enrollment;
use App\Service\EnrollmentService;
use App\Entity\InterestProfile;
use App\Service\RecommendationService; //<-ya está agregado
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


    /*
    #[Route('/courses', name: 'courses')]
    public function courses(
        EntityManagerInterface $em,
        RecommendationService $recommendationService
    ): Response {
        $student = $this->getUser();
        $courses = $em->getRepository(Course::class)->findBy(['isActive' => true]);
        $enrollments = $em->getRepository(Enrollment::class)->findBy(['student' => $student]);


        // Contar inscripciones por curso
        $enrollmentCounts = [];
        foreach ($courses as $course) {
            $enrollmentCounts[$course->getId()] = $em->getRepository(Enrollment::class)->count(['course' => $course]);
        }

        // Cursos ya inscritos
        $enrolledCourseIds = array_map(fn($e) => $e->getCourse()->getId(), $enrollments);

        // Recomendaciones (si tiene perfil)
        $recommendations = [];
        if ($student->getInterestProfile()) {
            $recommendations = $recommendationService->getForStudent($student); // ← Sin $this->get()
        }

        return $this->render('student/courses.html.twig', [
            'courses' => $courses,
            'enrollmentCounts' => $enrollmentCounts,
            'enrolledCourseIds' => $enrolledCourseIds,
            'recommendations' => $recommendations,
        ]);
    }*/


    #[Route('/courses', name: 'courses')]
    public function courses(
        Request $request,
        EntityManagerInterface $em,
        RecommendationService $recommendationService
    ): Response {
        $student = $this->getUser();
        $showAvailableOnly = (bool) $request->query->get('available', false);

        $qb = $em->getRepository(Course::class)->createQueryBuilder('c')
            ->where('c.isActive = true');
        // Filtrar por grado si existe
        if ($studentGrade = $student->getGrade()) {
            /*
            $qb->andWhere('c.targetGrade = :grade OR c.targetGrade IS NULL')
                ->setParameter('grade', $studentGrade);
            */
            $qb->andWhere('JSON_CONTAINS(c.targetGrades, :grade) = true OR JSON_LENGTH(c.targetGrades) = 0')
                ->setParameter('grade', '"' . $studentGrade . '"');
        }

        // Filtrar por disponibilidad
        if ($showAvailableOnly) {
            $qb->andWhere('(SELECT COUNT(e) FROM App\Entity\Enrollment e WHERE e.course = c.id) < c.maxCapacity');
        }

        $courses = $qb->getQuery()->getResult();
        $enrollments = $em->getRepository(Enrollment::class)->findBy(['student' => $student]);

        // Contar inscripciones por curso
        $enrollmentCounts = [];
        foreach ($courses as $course) {
            $enrollmentCounts[$course->getId()] = $em->getRepository(Enrollment::class)->count(['course' => $course]);
        }

        // Cursos ya inscritos
        $enrolledCourseIds = array_map(fn($e) => $e->getCourse()->getId(), $enrollments);

        // Recomendaciones con razones
        $recommendations = [];
        if ($student->getInterestProfile()) {
            $recommendations = $recommendationService->getForStudentWithReasons($student);
        }

        return $this->render('student/courses.html.twig', [
            'courses' => $courses,
            'enrollmentCounts' => $enrollmentCounts,
            'enrolledCourseIds' => $enrolledCourseIds,
            'recommendations' => $recommendations,
        ]);
    }

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

        // Validar: fecha límite
        if ($course->getEnrollmentDeadline() && $now > $course->getEnrollmentDeadline()) {
            $this->addFlash('error', 'La inscripción para este curso ya está cerrada.');
            return $this->redirectToRoute('student_courses');
        }

        // Validar: ya inscrito
        $existing = $em->getRepository(Enrollment::class)->findOneBy([
            'student' => $student,
            'course' => $course
        ]);
        if ($existing) {
            $this->addFlash('warning', 'Ya estás inscrito en este curso.');
            return $this->redirectToRoute('student_courses');
        }

        // Contar inscripciones actuales
        $currentCount = $em->getRepository(Enrollment::class)->count(['course' => $course]);
        if ($currentCount < $course->getMaxCapacity()) {
            // Hay cupo → inscribir
            $enrollment = new Enrollment();
            $enrollment->setStudent($student);
            $enrollment->setCourse($course);
            $enrollment->setEnrolledAt(new \DateTime());
            $em->persist($enrollment);
            $em->flush();
            $this->addFlash('success', '¡Inscripción exitosa!');
        } else {
            // Sin cupo → prioridad por promedio
            $studentGrade = $student->getAverageGrade();
            if ($studentGrade === null) {
                $this->addFlash('error', 'No puedes inscribirte sin promedio registrado.');
                return $this->redirectToRoute('student_courses');
            }

            // Buscar estudiante con menor promedio
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
            $this->addFlash('success', 'Perfil de intereses actualizado.');
            return $this->redirectToRoute('student_courses');
        }

        return $this->render('student/profile.html.twig', [
            'profile' => $profile,
        ]);
    }
}
