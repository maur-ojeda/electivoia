<?php

namespace App\Service;

use App\Entity\School;
use App\Entity\User;
use App\Repository\EnrollmentRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Analyzes whether the recommendation system treats different demographic
 * groups fairly by examining enrollment distribution by gender and grade.
 */
class BiasDetectionService
{
    /** Percentage points — deviation above this triggers a gender flag */
    private const GENDER_DEVIATION_THRESHOLD = 20.0;

    /** Percentage — a single grade above this share triggers a grade flag */
    private const GRADE_DOMINANCE_THRESHOLD = 70.0;

    public function __construct(
        private EntityManagerInterface $em,
        private EnrollmentRepository $enrollmentRepository,
    ) {}

    /**
     * Analyzes enrollment distribution by demographic group for a school.
     *
     * @return array{
     *     gender_bias: array<int, array{
     *         category: string,
     *         total_enrolled: int,
     *         male_pct: float,
     *         female_pct: float,
     *         school_male_pct: float,
     *         school_female_pct: float,
     *         deviation: float,
     *         flagged: bool
     *     }>,
     *     grade_bias: array<int, array{
     *         category: string,
     *         total_enrolled: int,
     *         grade_distribution: array<string, float>,
     *         dominant_grade: string,
     *         dominant_pct: float,
     *         flagged: bool
     *     }>,
     *     summary: array{
     *         total_categories_analyzed: int,
     *         gender_flags: int,
     *         grade_flags: int,
     *         overall_fair: bool
     *     }
     * }
     */
    public function analyzeBias(School $school): array
    {
        // 1. School-level gender baseline
        $schoolGender = $this->getSchoolGenderBaseline($school);
        $schoolMalePct = $schoolGender['male_pct'];
        $schoolFemalePct = $schoolGender['female_pct'];

        // 2. Enrollment demographics grouped by category
        $demographics = $this->enrollmentRepository->findEnrollmentDemographicsForSchool($school);

        if (empty($demographics)) {
            return $this->emptyReport();
        }

        $byCategory = $this->groupByCategory($demographics);

        // 3. Gender bias analysis
        $genderBias = [];
        foreach ($byCategory as $category => $data) {
            $genderBias[] = $this->analyzeGenderBias(
                $category,
                $data,
                $schoolMalePct,
                $schoolFemalePct,
            );
        }
        usort($genderBias, fn (array $a, array $b): int => $b['deviation'] <=> $a['deviation']);

        // 4. Grade bias analysis
        $gradeBias = [];
        foreach ($byCategory as $category => $data) {
            $gradeBias[] = $this->analyzeGradeBias($category, $data);
        }
        usort($gradeBias, fn (array $a, array $b): int => $b['dominant_pct'] <=> $a['dominant_pct']);

        // 5. Summary
        $genderFlags = count(array_filter($genderBias, fn (array $item): bool => $item['flagged']));
        $gradeFlags = count(array_filter($gradeBias, fn (array $item): bool => $item['flagged']));

        return [
            'gender_bias' => $genderBias,
            'grade_bias' => $gradeBias,
            'summary' => [
                'total_categories_analyzed' => count($byCategory),
                'gender_flags' => $genderFlags,
                'grade_flags' => $gradeFlags,
                'overall_fair' => $genderFlags === 0 && $gradeFlags === 0,
            ],
        ];
    }

    /**
     * Returns the school-level male/female percentage baseline.
     *
     * @return array{male_pct: float, female_pct: float}
     */
    private function getSchoolGenderBaseline(School $school): array
    {
        $userRepo = $this->em->getRepository(User::class);
        $counts = $userRepo->countByGenderForSchool($school);

        $male = $counts['M'] ?? 0;
        $female = $counts['F'] ?? 0;
        $total = $male + $female;

        return [
            'male_pct' => $total > 0 ? round(($male / $total) * 100, 1) : 0.0,
            'female_pct' => $total > 0 ? round(($female / $total) * 100, 1) : 0.0,
        ];
    }

    /**
     * Groups flat demographic rows into per-category aggregates.
     *
     * @param array<int, array{category: string, gender: ?string, grade: ?string}> $demographics
     *
     * @return array<string, array{total: int, gender: array{M: int, F: int, other: int}, grade: array<string, int>}>
     */
    private function groupByCategory(array $demographics): array
    {
        $byCategory = [];

        foreach ($demographics as $row) {
            $category = $row['category'];

            if (!isset($byCategory[$category])) {
                $byCategory[$category] = [
                    'total' => 0,
                    'gender' => ['M' => 0, 'F' => 0, 'other' => 0],
                    'grade' => [],
                ];
            }

            ++$byCategory[$category]['total'];

            $gender = $row['gender'];
            if ($gender === 'M') {
                ++$byCategory[$category]['gender']['M'];
            } elseif ($gender === 'F') {
                ++$byCategory[$category]['gender']['F'];
            } else {
                ++$byCategory[$category]['gender']['other'];
            }

            $grade = $row['grade'] ?? 'Sin grado';
            if ($grade === null) {
                $grade = 'Sin grado';
            }

            $byCategory[$category]['grade'][$grade] =
                ($byCategory[$category]['grade'][$grade] ?? 0) + 1;
        }

        return $byCategory;
    }

    /**
     * @param array{total: int, gender: array{M: int, F: int, other: int}, grade: array<string, int>} $data
     *
     * @return array{category: string, total_enrolled: int, male_pct: float, female_pct: float, school_male_pct: float, school_female_pct: float, deviation: float, flagged: bool}
     */
    private function analyzeGenderBias(
        string $category,
        array $data,
        float $schoolMalePct,
        float $schoolFemalePct,
    ): array {
        $maleCount = $data['gender']['M'];
        $femaleCount = $data['gender']['F'];
        $mfTotal = $maleCount + $femaleCount;

        $malePct = $mfTotal > 0 ? round(($maleCount / $mfTotal) * 100, 1) : 0.0;
        $femalePct = $mfTotal > 0 ? round(($femaleCount / $mfTotal) * 100, 1) : 0.0;

        $deviation = round(abs($femalePct - $schoolFemalePct), 1);

        return [
            'category' => $category,
            'total_enrolled' => $data['total'],
            'male_pct' => $malePct,
            'female_pct' => $femalePct,
            'school_male_pct' => $schoolMalePct,
            'school_female_pct' => $schoolFemalePct,
            'deviation' => $deviation,
            'flagged' => $deviation > self::GENDER_DEVIATION_THRESHOLD,
        ];
    }

    /**
     * @param array{total: int, gender: array{M: int, F: int, other: int}, grade: array<string, int>} $data
     *
     * @return array{category: string, total_enrolled: int, grade_distribution: array<string, float>, dominant_grade: string, dominant_pct: float, flagged: bool}
     */
    private function analyzeGradeBias(string $category, array $data): array
    {
        $total = $data['total'];

        $distribution = [];
        foreach ($data['grade'] as $grade => $count) {
            $distribution[$grade] = round(($count / $total) * 100, 1);
        }

        arsort($distribution);

        $dominantGrade = array_key_first($distribution) ?? 'Sin grado';
        $dominantPct = $distribution[$dominantGrade] ?? 0.0;

        return [
            'category' => $category,
            'total_enrolled' => $total,
            'grade_distribution' => $distribution,
            'dominant_grade' => $dominantGrade,
            'dominant_pct' => $dominantPct,
            'flagged' => $dominantPct > self::GRADE_DOMINANCE_THRESHOLD,
        ];
    }

    private function emptyReport(): array
    {
        return [
            'gender_bias' => [],
            'grade_bias' => [],
            'summary' => [
                'total_categories_analyzed' => 0,
                'gender_flags' => 0,
                'grade_flags' => 0,
                'overall_fair' => true,
            ],
        ];
    }
}
