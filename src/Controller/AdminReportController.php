<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\User;
use App\Service\GeminiInsightsService;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reports', name: 'admin_reports_')]
#[IsGranted('ROLE_ADMIN')]
class AdminReportController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private GeminiInsightsService $insightsService,
        private TenantContext $tenantContext
    ) {}

    private function activeCourseQb(): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->em->getRepository(Course::class)
            ->createQueryBuilder('c')
            ->where('c.isActive = true');

        if ($this->tenantContext->hasSchool()) {
            $qb->andWhere('c.school = :_school')
               ->setParameter('_school', $this->tenantContext->getCurrentSchool());
        }

        return $qb;
    }

    #[Route('', name: 'index')]
    public function index(): Response
    {
        // --- Datos para gráfico: Ocupación por curso ---
        $courses = $this->activeCourseQb()->getQuery()->getResult();

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
        $categoryData = $this->activeCourseQb()
            ->select('cat.name as category, COUNT(c.id) as count')
            ->join('c.category', 'cat')
            ->groupBy('cat.name')
            ->getQuery()
            ->getScalarResult();

        $categoryLabels = array_column($categoryData, 'category');
        $categoryCounts = array_column($categoryData, 'count');

        // --- Datos para gráfico: Carga de profesores ---
        $teacherQb = $this->em->getRepository(User::class)
            ->createQueryBuilder('u')
            ->select('u.fullName as teacherName, COUNT(c.id) as courseCount')
            ->join('u.coursesAsTeacher', 'c')
            ->where('c.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('u.id')
            ->orderBy('courseCount', 'DESC');

        if ($this->tenantContext->hasSchool()) {
            $teacherQb->andWhere('c.school = :_school')
                      ->setParameter('_school', $this->tenantContext->getCurrentSchool());
        }

        $teacherLoadData = $teacherQb->getQuery()->getScalarResult();

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
        $scienceQb = $this->activeCourseQb()
            ->join('c.category', 'cat')
            ->andWhere('cat.name = :area')
            ->setParameter('area', 'Ciencias');
        $scienceCourses = $scienceQb->getQuery()->getResult();

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
            'course_labels' => json_encode($courseLabels),
            'enrollment_data' => json_encode($enrollmentData),
            'capacity_data' => json_encode($capacityData),
            'category_labels' => json_encode($categoryLabels),
            'category_data' => json_encode($categoryCounts),
            'teacher_names' => json_encode($teacherNames),
            'teacher_courses' => json_encode($teacherCourses),
            'insights' => $insights,
        ]);
    }

    #[Route('/comparative', name: 'comparative')]
    public function comparative(): Response
    {
        $matrix = $this->buildComparativeMatrix();

        return $this->render('admin_report/comparative.html.twig', [
            'matrix'     => $matrix['matrix'],
            'grades'     => $matrix['grades'],
            'categories' => $matrix['categories'],
            'totals'     => $matrix['totals'],
        ]);
    }

    #[Route('/comparative/export', name: 'comparative_export')]
    public function comparativeExport(): StreamedResponse
    {
        $matrix = $this->buildComparativeMatrix();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Reporte Comparativo');

        // Header row
        $col = 2;
        foreach ($matrix['categories'] as $cat) {
            $sheet->setCellValueByColumnAndRow($col, 1, $cat);
            $col++;
        }
        $sheet->setCellValueByColumnAndRow($col, 1, 'TOTAL');

        // Data rows
        $row = 2;
        foreach ($matrix['grades'] as $grade) {
            $sheet->setCellValueByColumnAndRow(1, $row, $grade);
            $col = 2;
            $rowTotal = 0;
            foreach ($matrix['categories'] as $cat) {
                $val = $matrix['matrix'][$grade][$cat] ?? 0;
                $sheet->setCellValueByColumnAndRow($col, $row, $val);
                $rowTotal += $val;
                $col++;
            }
            $sheet->setCellValueByColumnAndRow($col, $row, $rowTotal);
            $row++;
        }

        // Totals row
        $sheet->setCellValueByColumnAndRow(1, $row, 'TOTAL');
        $col = 2;
        foreach ($matrix['categories'] as $cat) {
            $sheet->setCellValueByColumnAndRow($col, $row, $matrix['totals'][$cat] ?? 0);
            $col++;
        }

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="reporte_comparativo.xlsx"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    // ── HU-29: Cursos por profesor ───────────────────────────────────────────

    #[Route('/by-teacher', name: 'by_teacher')]
    public function byTeacher(): Response
    {
        $qb = $this->em->getRepository(Course::class)
            ->createQueryBuilder('c')
            ->orderBy('c.name', 'ASC');

        if ($this->tenantContext->hasSchool()) {
            $qb->andWhere('c.school = :_school')
               ->setParameter('_school', $this->tenantContext->getCurrentSchool());
        }

        $courses = $qb->getQuery()->getResult();

        $byTeacher = [];
        foreach ($courses as $course) {
            $teacher = $course->getTeacher();
            $teacherId = $teacher?->getId() ?? 0;
            if (!isset($byTeacher[$teacherId])) {
                $byTeacher[$teacherId] = [
                    'teacher' => $teacher,
                    'courses' => [],
                    'totalEnrolled' => 0,
                ];
            }
            $enrolled = $this->em->getRepository(Enrollment::class)->count(['course' => $course]);
            $byTeacher[$teacherId]['courses'][] = $course;
            $byTeacher[$teacherId]['totalEnrolled'] += $enrolled;
        }

        uasort($byTeacher, fn($a, $b) => count($b['courses']) <=> count($a['courses']));

        return $this->render('admin_report/by_teacher.html.twig', [
            'byTeacher' => $byTeacher,
        ]);
    }

    // ── HU-20: Alertas de baja inscripción ──────────────────────────────────

    #[Route('/low-enrollment', name: 'low_enrollment')]
    public function lowEnrollment(): Response
    {
        $threshold = 0.30;
        $courses = $this->activeCourseQb()->getQuery()->getResult();

        $alerts = [];
        foreach ($courses as $course) {
            $enrolled = $this->em->getRepository(Enrollment::class)->count(['course' => $course]);
            $capacity = $course->getMaxCapacity();
            $pct = $capacity > 0 ? $enrolled / $capacity : 0;

            if ($pct < $threshold) {
                $alerts[] = [
                    'course'    => $course,
                    'enrolled'  => $enrolled,
                    'capacity'  => $capacity,
                    'pct'       => round($pct * 100),
                ];
            }
        }

        usort($alerts, fn($a, $b) => $a['pct'] <=> $b['pct']);

        return $this->render('admin_report/low_enrollment.html.twig', [
            'alerts'    => $alerts,
            'threshold' => (int) ($threshold * 100),
        ]);
    }

    // ── HU-33: Insights IA ──────────────────────────────────────────────────

    #[Route('/insights', name: 'insights')]
    public function insights(): Response
    {
        $courses = $this->activeCourseQb()->getQuery()->getResult();

        $stats = [];
        $totalEnrolled = 0;
        $totalCapacity = 0;
        $byCategory = [];

        foreach ($courses as $course) {
            $enrolled = $this->em->getRepository(Enrollment::class)->count(['course' => $course]);
            $catName = $course->getCategory()?->getName() ?? 'Sin categoría';
            $totalEnrolled += $enrolled;
            $totalCapacity += $course->getMaxCapacity();
            $byCategory[$catName] = ($byCategory[$catName] ?? 0) + $enrolled;
        }

        arsort($byCategory);

        $stats = [
            'total_courses'   => count($courses),
            'total_enrolled'  => $totalEnrolled,
            'total_capacity'  => $totalCapacity,
            'avg_occupancy'   => $totalCapacity > 0 ? round($totalEnrolled / $totalCapacity * 100, 1) : 0,
            'by_category'     => $byCategory,
        ];

        $aiInsights = $this->insightsService->generateInsights($stats);

        return $this->render('admin_report/insights.html.twig', [
            'stats'      => $stats,
            'aiInsights' => $aiInsights,
        ]);
    }

    private function buildComparativeMatrix(): array
    {
        $grades = ['3M', '4M'];

        // Get all categories that have active courses (filtered by tenant)
        $catQb = $this->activeCourseQb()
            ->select('DISTINCT cat.name as catName')
            ->join('c.category', 'cat')
            ->orderBy('cat.name', 'ASC');
        $categoryRows = $catQb->getQuery()->getScalarResult();
        $categories = array_column($categoryRows, 'catName');

        // Enrollments grouped by grade + category (filtered by tenant)
        $enrollQb = $this->em->getRepository(Enrollment::class)
            ->createQueryBuilder('e')
            ->select('u.grade, cat.name as catName, COUNT(e.id) as cnt')
            ->join('e.student', 'u')
            ->join('e.course', 'c')
            ->join('c.category', 'cat')
            ->where('c.isActive = true')
            ->andWhere('u.grade IN (:grades)')
            ->setParameter('grades', $grades)
            ->groupBy('u.grade, cat.name');

        if ($this->tenantContext->hasSchool()) {
            $enrollQb->andWhere('c.school = :_school')
                     ->setParameter('_school', $this->tenantContext->getCurrentSchool());
        }

        $rows = $enrollQb->getQuery()->getScalarResult();

        $matrix = [];
        $totals = [];

        foreach ($rows as $row) {
            $matrix[$row['grade']][$row['catName']] = (int) $row['cnt'];
            $totals[$row['catName']] = ($totals[$row['catName']] ?? 0) + (int) $row['cnt'];
        }

        return compact('matrix', 'grades', 'categories', 'totals');
    }
}
