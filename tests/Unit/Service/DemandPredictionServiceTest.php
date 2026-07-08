<?php

namespace App\Tests\Unit\Service;

use App\Entity\Course;
use App\Entity\CourseCategory;
use App\Entity\School;
use App\Repository\AiSignalRepository;
use App\Repository\CourseRepository;
use App\Repository\InterestProfileRepository;
use App\Service\DemandPredictionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class DemandPredictionServiceTest extends TestCase
{
    private DemandPredictionService $service;
    private \PHPUnit\Framework\MockObject\MockObject $entityManager;
    private \PHPUnit\Framework\MockObject\MockObject $courseRepository;
    private \PHPUnit\Framework\MockObject\MockObject $interestProfileRepository;
    private \PHPUnit\Framework\MockObject\MockObject $aiSignalRepository;

    /** @var array<string, int> Tracks how many times each category was queried */
    private array $interestCounts = [];

    /** @var array<int, int> Tracks view counts per course ID */
    private array $viewCounts = [];

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->courseRepository = $this->createMock(CourseRepository::class);
        $this->interestProfileRepository = $this->createMock(InterestProfileRepository::class);
        $this->aiSignalRepository = $this->createMock(AiSignalRepository::class);

        $this->entityManager
            ->method('getRepository')
            ->willReturn($this->courseRepository);

        // Stub interest profile counts — default to 0
        $this->interestProfileRepository
            ->method('countStudentsInterestedInCategory')
            ->willReturnCallback(fn (int $schoolId, string $categoryName, int $minLevel = 3): int
                => $this->interestCounts[$categoryName] ?? 0);

        // Stub view counts — default to 0
        $this->aiSignalRepository
            ->method('countRecentViewsForCourse')
            ->willReturnCallback(fn (int $courseId, int $days = 30): int
                => $this->viewCounts[$courseId] ?? 0);

        $this->service = new DemandPredictionService(
            $this->entityManager,
            $this->interestProfileRepository,
            $this->aiSignalRepository,
        );
    }

    private function createSchool(int $id = 1): School
    {
        $school = $this->createMock(School::class);
        $school->method('getId')->willReturn($id);
        return $school;
    }

    private function createCourse(
        int $id = 1,
        string $name = 'Curso de prueba',
        string $categoryName = 'Ciencias',
        int $maxCapacity = 30,
        int $currentEnrollment = 10,
        ?School $school = null,
    ): Course {
        $course = $this->createMock(Course::class);
        $course->method('getId')->willReturn($id);
        $course->method('getName')->willReturn($name);
        $course->method('getMaxCapacity')->willReturn($maxCapacity);
        $course->method('getCurrentEnrollment')->willReturn($currentEnrollment);
        $course->method('getSchool')->willReturn($school ?? $this->createSchool());

        $category = $this->createMock(CourseCategory::class);
        $category->method('getName')->willReturn($categoryName);
        $course->method('getCategory')->willReturn($category);

        return $course;
    }

    // --- Test 1: Prediction uses interest pool correctly ---

    public function testPredictionUsesInterestPool(): void
    {
        $course = $this->createCourse(id: 1, maxCapacity: 50, currentEnrollment: 5);
        $this->interestCounts['Ciencias'] = 20;

        $result = $this->service->predictDemandForCourse($course);

        // demand_pool = 20, view_boost = 0, predicted = min(20, 50) = 20
        $this->assertEquals(20, $result['predicted_enrollments']);
    }

    // --- Test 2: View signals boost prediction ---

    public function testViewSignalsBoostPrediction(): void
    {
        $course = $this->createCourse(id: 1, maxCapacity: 100, currentEnrollment: 0);
        $this->interestCounts['Ciencias'] = 10;
        $this->viewCounts[1] = 50; // 50 views → boost = 5.0

        $result = $this->service->predictDemandForCourse($course);

        // demand_pool = 10, view_boost = 50 * 0.1 = 5.0, predicted = min(15.0, 100) = 15
        $this->assertEquals(15, $result['predicted_enrollments']);
    }

    // --- Test 3: Prediction is capped at max_capacity ---

    public function testPredictionCappedAtMaxCapacity(): void
    {
        $course = $this->createCourse(id: 1, maxCapacity: 25, currentEnrollment: 5);
        $this->interestCounts['Ciencias'] = 100; // way above capacity
        $this->viewCounts[1] = 200; // huge boost

        $result = $this->service->predictDemandForCourse($course);

        // demand_pool = 100, view_boost = 200 * 0.1 = 20, raw = 120, capped at 25
        $this->assertEquals(25, $result['predicted_enrollments']);
    }

    // --- Test 4: Confidence calculation ---

    public function testConfidenceCalculation(): void
    {
        // demand_pool = 15, max_capacity = 30 → confidence = 15/30 = 0.5
        $course = $this->createCourse(id: 1, maxCapacity: 30, currentEnrollment: 0);
        $this->interestCounts['Ciencias'] = 15;

        $result = $this->service->predictDemandForCourse($course);

        $this->assertEquals(0.5, $result['confidence']);
    }

    public function testConfidenceCappedAtOne(): void
    {
        // demand_pool = 60, max_capacity = 30 → confidence = min(1.0, 2.0) = 1.0
        $course = $this->createCourse(id: 1, maxCapacity: 30, currentEnrollment: 0);
        $this->interestCounts['Ciencias'] = 60;

        $result = $this->service->predictDemandForCourse($course);

        $this->assertEquals(1.0, $result['confidence']);
    }

    public function testConfidenceZeroWhenNoInterest(): void
    {
        $course = $this->createCourse(id: 1, maxCapacity: 30, currentEnrollment: 0);
        $this->interestCounts['Ciencias'] = 0;

        $result = $this->service->predictDemandForCourse($course);

        $this->assertEquals(0.0, $result['confidence']);
    }

    // --- Test 5: Factors are generated in Spanish ---

    public function testFactorsGeneratedInSpanish(): void
    {
        $course = $this->createCourse(id: 1, maxCapacity: 40, currentEnrollment: 8);
        $this->interestCounts['Ciencias'] = 15;
        $this->viewCounts[1] = 30;

        $result = $this->service->predictDemandForCourse($course);

        $this->assertCount(3, $result['factors']);

        // Factor 1: interest pool
        $this->assertStringContainsString('15 estudiantes tienen interés en esta área', $result['factors'][0]);

        // Factor 2: views
        $this->assertStringContainsString('30 vistas recientes del curso', $result['factors'][1]);

        // Factor 3: historical rate (8/40 = 20%)
        $this->assertStringContainsString('Tasa histórica de inscripción: 20%', $result['factors'][2]);
    }

    // --- Test 6: Empty school returns empty predictions ---

    public function testEmptySchoolReturnsEmptyPredictions(): void
    {
        $school = $this->createSchool();

        $this->courseRepository
            ->method('findBy')
            ->willReturn([]);

        $result = $this->service->predictDemandForSchool($school);

        $this->assertSame([], $result);
    }

    // --- Test 7: predictDemandForSchool returns all courses sorted by predicted demand ---

    public function testPredictDemandForSchoolReturnsSortedPredictions(): void
    {
        $school = $this->createSchool();

        $courseLow = $this->createCourse(id: 1, name: 'Curso bajo', categoryName: 'Artes', maxCapacity: 30);
        $courseHigh = $this->createCourse(id: 2, name: 'Curso alto', categoryName: 'Ciencias', maxCapacity: 30);

        $this->interestCounts['Artes'] = 5;
        $this->interestCounts['Ciencias'] = 25;

        $this->courseRepository
            ->method('findBy')
            ->willReturn([$courseLow, $courseHigh]);

        $result = $this->service->predictDemandForSchool($school);

        $this->assertCount(2, $result);

        // Sorted by predicted_enrollments descending: Ciencias (25) first
        $this->assertEquals(2, $result[0]['course']->getId());
        $this->assertEquals(25, $result[0]['predicted_enrollments']);
        $this->assertEquals(25, $result[0]['demand_pool']);

        $this->assertEquals(1, $result[1]['course']->getId());
        $this->assertEquals(5, $result[1]['predicted_enrollments']);
        $this->assertEquals(5, $result[1]['demand_pool']);
    }

    // --- Test 8: Course without school returns safe fallback ---

    public function testCourseWithoutSchoolReturnsFallback(): void
    {
        $course = $this->createMock(Course::class);
        $course->method('getId')->willReturn(1);
        $course->method('getMaxCapacity')->willReturn(30);
        $course->method('getCurrentEnrollment')->willReturn(5);
        $course->method('getSchool')->willReturn(null);
        $course->method('getCategory')->willReturn($this->createMock(CourseCategory::class));

        $result = $this->service->predictDemandForCourse($course);

        $this->assertEquals(5, $result['predicted_enrollments']);
        $this->assertEquals(0.0, $result['confidence']);
    }
}
