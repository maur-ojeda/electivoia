<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reports', name: 'admin_reports_')]
#[IsGranted('ROLE_ADMIN')]
class AdminReportController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('', name: 'index')]
    public function index(): Response
    {
        // --- Datos para gráfico: Ocupación por curso ---
        $courses = $this->em->getRepository(Course::class)->findBy(['isActive' => true]);

        $courseLabels = [];
        $enrollmentData = [];
        $capacityData = [];

        foreach ($courses as $course) {
            $currentEnrollment = $this->em->getRepository(\App\Entity\Enrollment::class)
                ->count(['course' => $course]);

            $courseLabels[] = $course->getName();
            $enrollmentData[] = $currentEnrollment;
            $capacityData[] = $course->getMaxCapacity();
        }

        // --- Datos para gráfico: Cursos por categoría ---
        $categoryData = $this->em->getRepository(Course::class)
            ->createQueryBuilder('c')
            ->select('cat.name as category, COUNT(c.id) as count')
            ->join('c.category', 'cat')
            ->groupBy('cat.name')
            ->getQuery()
            ->getScalarResult();

        $categoryLabels = array_column($categoryData, 'category');
        $categoryCounts = array_column($categoryData, 'count');

        // --- Datos para gráfico: Carga de profesores ---
        $teacherLoadData = $this->em->getRepository(User::class)
            ->createQueryBuilder('u')
            ->select('u.fullName as teacherName, COUNT(c.id) as courseCount')
            ->join('u.coursesAsTeacher', 'c')
            ->where('c.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('u.id')
            ->orderBy('courseCount', 'DESC')
            ->getQuery()
            ->getScalarResult();

        $teacherNames = array_column($teacherLoadData, 'teacherName');
        $teacherCourses = array_column($teacherLoadData, 'courseCount');

        // --- Generar insights en lenguaje natural ---
        $totalCourses = count($courses);
        $totalEnrollments = array_sum($enrollmentData);
        $averageOccupancy = $totalCourses > 0 ? ($totalEnrollments / array_sum($capacityData)) * 100 : 0;

        // Insight: Porcentaje de cursos con alta demanda (>80% de cupo lleno)
        $highDemandCount = 0;
        foreach ($courses as $course) {
            $current = $this->em->getRepository(\App\Entity\Enrollment::class)->count(['course' => $course]);
            if ($course->getMaxCapacity() > 0 && ($current / $course->getMaxCapacity()) >= 0.8) {
                $highDemandCount++;
            }
        }
        $highDemandRatio = $totalCourses > 0 ? ($highDemandCount / $totalCourses) * 100 : 0;

        // Insight específico por área
        $scienceCourses = $this->em->getRepository(Course::class)
            ->createQueryBuilder('c')
            ->join('c.category', 'cat')
            ->where('cat.name = :area AND c.isActive = :active')
            ->setParameter('area', 'Ciencias')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();

        $scienceEnrollments = 0;
        $scienceCapacity = 0;
        foreach ($scienceCourses as $course) {
            $current = $this->em->getRepository(\App\Entity\Enrollment::class)->count(['course' => $course]);
            $scienceEnrollments += $current;
            $scienceCapacity += $course->getMaxCapacity();
        }
        $scienceOccupancy = $scienceCapacity > 0 ? ($scienceEnrollments / $scienceCapacity) * 100 : 0;

        $insights = [
            "El sistema tiene un total de {$totalCourses} cursos activos con {$totalEnrollments} inscripciones.",
            sprintf("La ocupación promedio general es del %.1f%%.", $averageOccupancy),
            sprintf("El %.1f%% de los cursos tienen más del 80%% de su cupo ocupado.", $highDemandRatio),
            sprintf("Los cursos de 'Ciencias' tienen una ocupación del %.1f%%.", $scienceOccupancy),
            "Se recomienda revisar los cursos con baja inscripción para ajustar oferta o promoción."
        ];


        return $this->render('admin_report/index.html.twig', [
            'courses' => $courses,
            // Datos para gráficos
            'course_labels' => json_encode($courseLabels),
            'enrollment_data' => json_encode($enrollmentData),
            'capacity_data' => json_encode($capacityData),

            'category_labels' => json_encode($categoryLabels),
            'category_data' => json_encode($categoryCounts),

            'teacher_names' => json_encode($teacherNames),
            'teacher_courses' => json_encode($teacherCourses),

            // Insights
            'insights' => $insights,
        ]);
    }
}
