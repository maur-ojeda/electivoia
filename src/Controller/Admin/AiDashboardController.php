<?php

namespace App\Controller\Admin;

use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\InterestProfile;
use App\Entity\User;
use App\Repository\AiSignalRepository;
use App\Repository\EnrollmentRepository;
use App\Repository\InterestProfileRepository;
use App\Service\BiasDetectionService;
use App\Service\DemandPredictionService;
use App\Service\RecommendationService;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/ai', name: 'admin_ai_')]
#[IsGranted('ROLE_ADMIN')]
class AiDashboardController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private RecommendationService $recommendationService,
        private DemandPredictionService $demandPredictionService,
        private BiasDetectionService $biasDetectionService,
        private AiSignalRepository $aiSignalRepository,
        private EnrollmentRepository $enrollmentRepository,
        private TenantContext $tenantContext,
    ) {}

    #[Route('', name: 'dashboard')]
    public function index(): Response
    {
        $school = $this->tenantContext->getCurrentSchool();

        // --- 1. Engine Overview ---
        $engineOverview = $this->buildEngineOverview($school);

        // --- 2. Demand Predictions ---
        $predictions = $school !== null
            ? $this->demandPredictionService->predictDemandForSchool($school)
            : [];

        // --- 3. Signal Activity (last 30 days) ---
        $since = new \DateTimeImmutable('-30 days');
        $signalCounts = $this->aiSignalRepository->countByActionSince($since, $school);

        // --- 4. Recommendation Coverage ---
        $coverage = $this->calculateCoverage($school);

        // --- 5. Bias Detection ---
        $biasReport = $school !== null
            ? $this->biasDetectionService->analyzeBias($school)
            : null;

        return $this->render('admin/ai_dashboard.html.twig', [
            'engine_overview' => $engineOverview,
            'predictions' => $predictions,
            'signal_counts' => $signalCounts,
            'coverage' => $coverage,
            'bias_report' => $biasReport,
            'school' => $school,
        ]);
    }

    /**
     * @return array{total_enrollments: int, active_courses: int, students_with_profiles: int}
     */
    private function buildEngineOverview(?object $school): array
    {
        // Active courses count
        $courseQb = $this->em->getRepository(Course::class)
            ->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.isActive = true');

        if ($school !== null) {
            $courseQb->andWhere('c.school = :school')
                ->setParameter('school', $school);
        }

        $activeCourses = (int) $courseQb->getQuery()->getSingleScalarResult();

        // Total enrollments for active courses
        $enrollmentQb = $this->em->getRepository(Enrollment::class)
            ->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->join('e.course', 'c')
            ->where('c.isActive = true');

        if ($school !== null) {
            $enrollmentQb->andWhere('c.school = :school')
                ->setParameter('school', $school);
        }

        $totalEnrollments = (int) $enrollmentQb->getQuery()->getSingleScalarResult();

        // Students with interest profiles
        $studentsWithProfiles = 0;
        if ($school !== null && $school->getId() !== null) {
            /** @var InterestProfileRepository $profileRepo */
            $profileRepo = $this->em->getRepository(InterestProfile::class);
            $studentsWithProfiles = $profileRepo->countProfilesInSchool($school->getId());
        }

        return [
            'total_enrollments' => $totalEnrollments,
            'active_courses' => $activeCourses,
            'students_with_profiles' => $studentsWithProfiles,
        ];
    }

    /**
     * Calculates the percentage of active courses that have at least one
     * student interested in their category (level >= 3).
     *
     * @return array{covered: int, total: int, percentage: float}
     */
    private function calculateCoverage(?object $school): array
    {
        $courseQb = $this->em->getRepository(Course::class)
            ->createQueryBuilder('c')
            ->where('c.isActive = true');

        if ($school !== null) {
            $courseQb->andWhere('c.school = :school')
                ->setParameter('school', $school);
        }

        $courses = $courseQb->getQuery()->getResult();

        if (empty($courses)) {
            return ['covered' => 0, 'total' => 0, 'percentage' => 0.0];
        }

        /** @var InterestProfileRepository $profileRepo */
        $profileRepo = $this->em->getRepository(InterestProfile::class);
        $covered = 0;

        foreach ($courses as $course) {
            $category = $course->getCategory();

            if ($category === null || $school === null || $school->getId() === null) {
                continue;
            }

            $interestedCount = $profileRepo->countStudentsInterestedInCategory(
                $school->getId(),
                $category->getName(),
                3,
            );

            if ($interestedCount > 0) {
                ++$covered;
            }
        }

        $total = count($courses);
        $percentage = $total > 0 ? round(($covered / $total) * 100, 1) : 0.0;

        return [
            'covered' => $covered,
            'total' => $total,
            'percentage' => $percentage,
        ];
    }
}
