<?php

namespace App\Service;

use App\Entity\Course;
use App\Entity\School;
use App\Repository\AiSignalRepository;
use App\Repository\InterestProfileRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Predicts enrollment demand for courses based on interest signals, view activity,
 * and historical enrollment trends.
 */
class DemandPredictionService
{
    private const VIEW_BOOST_FACTOR = 0.1;
    private const RECENT_VIEWS_DAYS = 30;
    private const INTEREST_THRESHOLD = 3;

    public function __construct(
        private EntityManagerInterface $em,
        private InterestProfileRepository $interestProfileRepository,
        private AiSignalRepository $aiSignalRepository,
    ) {}

    /**
     * Predicts enrollment demand for a single course.
     *
     * Algorithm:
     * 1. Demand pool: students in the course's school whose interest profile
     *    matches the course category at level >= 3.
     * 2. View boost: recent 'view' signals for the course (last 30 days) × 0.1.
     * 3. Predicted = min(demand_pool + view_boost, max_capacity).
     * 4. Confidence = min(1.0, demand_pool / max_capacity).
     *
     * @return array{predicted_enrollments: int, confidence: float, factors: string[]}
     */
    public function predictDemandForCourse(Course $course): array
    {
        $maxCapacity = $course->getMaxCapacity() ?? 0;
        $currentEnrollment = $course->getCurrentEnrollment();
        $category = $course->getCategory();
        $school = $course->getSchool();

        // Cannot predict without a school or category
        if ($school === null || $school->getId() === null || $category === null) {
            return [
                'predicted_enrollments' => $currentEnrollment,
                'confidence' => 0.0,
                'factors' => ['Sin datos suficientes para predecir la demanda'],
            ];
        }

        $categoryName = $category->getName();

        // 1. Demand pool: students interested in this category
        $demandPool = $this->interestProfileRepository->countStudentsInterestedInCategory(
            $school->getId(),
            $categoryName,
            self::INTEREST_THRESHOLD,
        );

        // 2. View signals from the last 30 days
        $recentViews = $course->getId() !== null
            ? $this->aiSignalRepository->countRecentViewsForCourse($course->getId(), self::RECENT_VIEWS_DAYS)
            : 0;

        $viewBoost = $recentViews * self::VIEW_BOOST_FACTOR;

        // 3. Predicted enrollment (capped at max_capacity)
        $rawPrediction = $demandPool + $viewBoost;
        $predicted = $maxCapacity > 0
            ? (int) min($rawPrediction, $maxCapacity)
            : (int) round($rawPrediction);

        // 4. Confidence: higher demand pool relative to capacity = higher confidence
        $confidence = $maxCapacity > 0
            ? min(1.0, $demandPool / $maxCapacity)
            : 0.0;

        // 5. Human-readable factors (Spanish)
        $factors = $this->buildFactors($demandPool, $recentViews, $currentEnrollment, $maxCapacity);

        return [
            'predicted_enrollments' => $predicted,
            'confidence' => round($confidence, 2),
            'factors' => $factors,
        ];
    }

    /**
     * Predicts demand for all active courses in a school.
     *
     * @return array<int, array{course: Course, predicted_enrollments: int, confidence: float, factors: string[], demand_pool: int}>
     */
    public function predictDemandForSchool(School $school): array
    {
        if ($school->getId() === null) {
            return [];
        }

        $courses = $this->em->getRepository(Course::class)->findBy([
            'school' => $school,
            'isActive' => true,
        ]);

        $predictions = [];

        foreach ($courses as $course) {
            $prediction = $this->predictDemandForCourse($course);

            // Include demand pool size for the dashboard
            $category = $course->getCategory();
            $demandPool = 0;

            if ($category !== null) {
                $demandPool = $this->interestProfileRepository->countStudentsInterestedInCategory(
                    $school->getId(),
                    $category->getName(),
                    self::INTEREST_THRESHOLD,
                );
            }

            $predictions[] = [
                'course' => $course,
                'predicted_enrollments' => $prediction['predicted_enrollments'],
                'confidence' => $prediction['confidence'],
                'factors' => $prediction['factors'],
                'demand_pool' => $demandPool,
            ];
        }

        // Sort by predicted enrollments descending
        usort($predictions, fn (array $a, array $b): int => $b['predicted_enrollments'] <=> $a['predicted_enrollments']);

        return $predictions;
    }

    /**
     * @return string[]
     */
    private function buildFactors(int $demandPool, int $recentViews, int $currentEnrollment, int $maxCapacity): array
    {
        $factors = [];

        $factors[] = sprintf('%d estudiantes tienen interés en esta área', $demandPool);
        $factors[] = sprintf('%d vistas recientes del curso', $recentViews);

        if ($maxCapacity > 0) {
            $historicalRate = round(($currentEnrollment / $maxCapacity) * 100);
            $factors[] = sprintf('Tasa histórica de inscripción: %d%%', (int) $historicalRate);
        } else {
            $factors[] = 'Tasa histórica de inscripción: N/A';
        }

        return $factors;
    }
}
