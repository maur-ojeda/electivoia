<?php

namespace App\Tests\Unit\Service;

use App\Entity\School;
use App\Repository\EnrollmentRepository;
use App\Repository\UserRepository;
use App\Service\BiasDetectionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class BiasDetectionServiceTest extends TestCase
{
    private BiasDetectionService $service;
    private \PHPUnit\Framework\MockObject\MockObject $entityManager;
    private \PHPUnit\Framework\MockObject\MockObject $enrollmentRepository;
    private \PHPUnit\Framework\MockObject\MockObject $userRepository;

    /** @var array<int, array{category: string, gender: ?string, grade: ?string}> */
    private array $demographics = [];

    /** @var array{M: int, F: int} */
    private array $genderCounts = ['M' => 0, 'F' => 0];

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->enrollmentRepository = $this->createMock(EnrollmentRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);

        $this->entityManager
            ->method('getRepository')
            ->willReturn($this->userRepository);

        // Stub enrollment demographics — tests populate $this->demographics
        $this->enrollmentRepository
            ->method('findEnrollmentDemographicsForSchool')
            ->willReturnCallback(fn (): array => $this->demographics);

        // Stub gender counts — tests populate $this->genderCounts
        $this->userRepository
            ->method('countByGenderForSchool')
            ->willReturnCallback(fn (): array => $this->genderCounts);

        $this->service = new BiasDetectionService(
            $this->entityManager,
            $this->enrollmentRepository,
        );
    }

    private function createSchool(int $id = 1): School
    {
        $school = $this->createMock(School::class);
        $school->method('getId')->willReturn($id);
        return $school;
    }

    /**
     * Generates demographic rows for a category.
     *
     * @param array<int, array{gender: string, grade: ?string, count: int}> $specs
     *
     * @return array<int, array{category: string, gender: string, grade: ?string}>
     */
    private function makeRows(string $category, array $specs): array
    {
        $rows = [];

        foreach ($specs as $spec) {
            for ($i = 0; $i < $spec['count']; ++$i) {
                $rows[] = [
                    'category' => $category,
                    'gender' => $spec['gender'],
                    'grade' => $spec['grade'],
                ];
            }
        }

        return $rows;
    }

    // ───────────────────────────────────────────────────────────────────
    //  Test 1: Gender bias detection when deviation exceeds 20 pp
    // ───────────────────────────────────────────────────────────────────

    public function testGenderBiasFlaggedWhenDeviationExceedsThreshold(): void
    {
        $this->genderCounts = ['M' => 55, 'F' => 45]; // 55 % M / 45 % F

        $this->demographics = $this->makeRows('Artes', [
            ['gender' => 'M', 'grade' => '3M', 'count' => 2],
            ['gender' => 'M', 'grade' => '4M', 'count' => 2],
            ['gender' => 'F', 'grade' => '3M', 'count' => 8],
            ['gender' => 'F', 'grade' => '4M', 'count' => 8],
        ]);

        $result = $this->service->analyzeBias($this->createSchool());

        $this->assertCount(1, $result['gender_bias']);

        $bias = $result['gender_bias'][0];
        $this->assertSame('Artes', $bias['category']);
        $this->assertSame(20, $bias['total_enrolled']);
        $this->assertSame(20.0, $bias['male_pct']);
        $this->assertSame(80.0, $bias['female_pct']);
        $this->assertSame(55.0, $bias['school_male_pct']);
        $this->assertSame(45.0, $bias['school_female_pct']);
        $this->assertSame(35.0, $bias['deviation']);
        $this->assertTrue($bias['flagged']);
    }

    // ───────────────────────────────────────────────────────────────────
    //  Test 2: No gender bias flag when distribution is within tolerance
    // ───────────────────────────────────────────────────────────────────

    public function testNoGenderBiasWhenWithinTolerance(): void
    {
        $this->genderCounts = ['M' => 50, 'F' => 50]; // 50 % M / 50 % F

        $this->demographics = $this->makeRows('Ciencias', [
            ['gender' => 'M', 'grade' => '3M', 'count' => 6],
            ['gender' => 'M', 'grade' => '4M', 'count' => 5],
            ['gender' => 'F', 'grade' => '3M', 'count' => 5],
            ['gender' => 'F', 'grade' => '4M', 'count' => 4],
        ]);

        $result = $this->service->analyzeBias($this->createSchool());

        $bias = $result['gender_bias'][0];
        // 11 M / 9 F → 55 % M / 45 % F, school = 50/50, deviation = 5
        $this->assertSame(5.0, $bias['deviation']);
        $this->assertFalse($bias['flagged']);
    }

    // ───────────────────────────────────────────────────────────────────
    //  Test 3: Grade bias detection when one grade dominates > 70 %
    // ───────────────────────────────────────────────────────────────────

    public function testGradeBiasFlaggedWhenOneGradeDominates(): void
    {
        $this->genderCounts = ['M' => 50, 'F' => 50];

        $this->demographics = $this->makeRows('Tecnología', [
            ['gender' => 'M', 'grade' => '3M', 'count' => 6],
            ['gender' => 'M', 'grade' => '4M', 'count' => 2],
            ['gender' => 'F', 'grade' => '3M', 'count' => 5],
            ['gender' => 'F', 'grade' => '4M', 'count' => 2],
        ]);

        $result = $this->service->analyzeBias($this->createSchool());

        $grade = $result['grade_bias'][0];
        $this->assertSame('Tecnología', $grade['category']);
        $this->assertSame(15, $grade['total_enrolled']);
        $this->assertSame('3M', $grade['dominant_grade']);
        // 11 / 15 = 73.3 %
        $this->assertSame(73.3, $grade['dominant_pct']);
        $this->assertTrue($grade['flagged']);
    }

    // ───────────────────────────────────────────────────────────────────
    //  Test 4: No grade bias when distribution is balanced
    // ───────────────────────────────────────────────────────────────────

    public function testNoGradeBiasWhenBalanced(): void
    {
        $this->genderCounts = ['M' => 50, 'F' => 50];

        $this->demographics = $this->makeRows('Deportes', [
            ['gender' => 'M', 'grade' => '3M', 'count' => 3],
            ['gender' => 'M', 'grade' => '4M', 'count' => 2],
            ['gender' => 'F', 'grade' => '3M', 'count' => 2],
            ['gender' => 'F', 'grade' => '4M', 'count' => 3],
        ]);

        $result = $this->service->analyzeBias($this->createSchool());

        $grade = $result['grade_bias'][0];
        // 5 in 3M, 5 in 4M → 50 % / 50 %
        $this->assertSame(50.0, $grade['dominant_pct']);
        $this->assertFalse($grade['flagged']);
    }

    // ───────────────────────────────────────────────────────────────────
    //  Test 5: Summary counts are correct
    // ───────────────────────────────────────────────────────────────────

    public function testSummaryCountsAreCorrect(): void
    {
        $this->genderCounts = ['M' => 50, 'F' => 50];

        // Category A — gender bias (4 M / 16 F, deviation 30) + balanced grades
        // Category B — gender fine + grade bias (11 in 3M, 4 in 4M)
        // Category C — all balanced
        $this->demographics = array_merge(
            $this->makeRows('Artes', [
                ['gender' => 'M', 'grade' => '3M', 'count' => 2],
                ['gender' => 'M', 'grade' => '4M', 'count' => 2],
                ['gender' => 'F', 'grade' => '3M', 'count' => 8],
                ['gender' => 'F', 'grade' => '4M', 'count' => 8],
            ]),
            $this->makeRows('Tecnología', [
                ['gender' => 'M', 'grade' => '3M', 'count' => 4],
                ['gender' => 'M', 'grade' => '4M', 'count' => 3],
                ['gender' => 'F', 'grade' => '3M', 'count' => 4],
                ['gender' => 'F', 'grade' => '4M', 'count' => 4],
            ]),
            $this->makeRows('Ciencias', [
                ['gender' => 'M', 'grade' => '3M', 'count' => 5],
                ['gender' => 'M', 'grade' => '4M', 'count' => 5],
                ['gender' => 'F', 'grade' => '3M', 'count' => 5],
                ['gender' => 'F', 'grade' => '4M', 'count' => 5],
            ]),
        );

        $result = $this->service->analyzeBias($this->createSchool());

        $summary = $result['summary'];
        $this->assertSame(3, $summary['total_categories_analyzed']);
        $this->assertSame(1, $summary['gender_flags']); // only Artes
        $this->assertSame(0, $summary['grade_flags']); // no category exceeds 70 %
    }

    // ───────────────────────────────────────────────────────────────────
    //  Test 6: Overall fair is true when no flags
    // ───────────────────────────────────────────────────────────────────

    public function testOverallFairWhenNoFlags(): void
    {
        $this->genderCounts = ['M' => 50, 'F' => 50];

        $this->demographics = $this->makeRows('Ciencias', [
            ['gender' => 'M', 'grade' => '3M', 'count' => 5],
            ['gender' => 'M', 'grade' => '4M', 'count' => 5],
            ['gender' => 'F', 'grade' => '3M', 'count' => 5],
            ['gender' => 'F', 'grade' => '4M', 'count' => 5],
        ]);

        $result = $this->service->analyzeBias($this->createSchool());

        $this->assertTrue($result['summary']['overall_fair']);
        $this->assertSame(0, $result['summary']['gender_flags']);
        $this->assertSame(0, $result['summary']['grade_flags']);
    }

    // ───────────────────────────────────────────────────────────────────
    //  Test 7: Empty school returns clean report with zeros
    // ───────────────────────────────────────────────────────────────────

    public function testEmptySchoolReturnsCleanReport(): void
    {
        $this->genderCounts = ['M' => 0, 'F' => 0];
        $this->demographics = [];

        $result = $this->service->analyzeBias($this->createSchool());

        $this->assertSame([], $result['gender_bias']);
        $this->assertSame([], $result['grade_bias']);

        $summary = $result['summary'];
        $this->assertSame(0, $summary['total_categories_analyzed']);
        $this->assertSame(0, $summary['gender_flags']);
        $this->assertSame(0, $summary['grade_flags']);
        $this->assertTrue($summary['overall_fair']);
    }
}
