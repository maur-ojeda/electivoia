<?php

namespace App\Tests\Unit\Service;

use App\Entity\AiSignal;
use App\Entity\Course;
use App\Entity\School;
use App\Entity\User;
use App\Repository\AiSignalRepository;
use App\Service\SignalCollectionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class SignalCollectionServiceTest extends TestCase
{
    private \PHPUnit\Framework\MockObject\MockObject $signalRepository;
    private \PHPUnit\Framework\MockObject\MockObject $entityManager;
    private SignalCollectionService $service;

    protected function setUp(): void
    {
        $this->signalRepository = $this->createMock(AiSignalRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->service = new SignalCollectionService(
            $this->signalRepository,
            $this->entityManager,
        );
    }

    private function createStudent(int $id = 1): User
    {
        $student = $this->createMock(User::class);
        $student->method('getId')->willReturn($id);
        $student->method('getSchool')->willReturn(null);

        return $student;
    }

    // --- Test 1: recording a signal persists correctly ---

    public function testRecordPersistsSignal(): void
    {
        $student = $this->createStudent();
        $course = $this->createMock(Course::class);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (AiSignal $signal) use ($student, $course): bool {
                return $signal->getStudent() === $student
                    && $signal->getCourse() === $course
                    && $signal->getAction() === AiSignal::ACTION_VIEW
                    && $signal->getCreatedAt() !== null;
            }));

        $signal = $this->service->record($student, AiSignal::ACTION_VIEW, $course);

        $this->assertSame($student, $signal->getStudent());
        $this->assertSame($course, $signal->getCourse());
        $this->assertSame(AiSignal::ACTION_VIEW, $signal->getAction());
        $this->assertNotNull($signal->getCreatedAt());
    }

    // --- Test 2: getCourseIdsViewedByStudent delegates to repository ---

    public function testGetCourseIdsViewedByStudentReturnsViewedCourses(): void
    {
        $student = $this->createStudent();
        $expectedIds = [10, 20, 30];

        $this->signalRepository
            ->expects($this->once())
            ->method('findViewedCourseIds')
            ->with($student)
            ->willReturn($expectedIds);

        $result = $this->service->getCourseIdsViewedByStudent($student);

        $this->assertSame($expectedIds, $result);
    }

    // --- Test 3: signal boost for previously viewed course ---

    public function testSignalBoostForPreviouslyViewedCourse(): void
    {
        $student = $this->createStudent();

        $course = $this->createMock(Course::class);
        $course->method('getId')->willReturn(10);
        $course->method('getCategory')->willReturn(null);

        $this->signalRepository
            ->method('findViewedCourseIds')
            ->willReturn([10, 20]);

        // Use a real RecommendationService with mocked dependencies
        $recommendationService = $this->createRecommendationService();

        $boost = $recommendationService->getSignalBoost($student, $course);

        // Course 10 was viewed → +3, no category → +0 = 3
        $this->assertEquals(3, $boost);
    }

    // --- Test 4: signal boost for category pattern (3+ views) ---

    public function testSignalBoostForCategoryPattern(): void
    {
        $student = $this->createStudent();

        $category = $this->createMock(\App\Entity\CourseCategory::class);
        $category->method('getId')->willReturn(5);

        $course = $this->createMock(Course::class);
        $course->method('getId')->willReturn(99); // not in viewed list
        $course->method('getCategory')->willReturn($category);

        $this->signalRepository
            ->method('findViewedCourseIds')
            ->willReturn([10, 20, 30]); // course 99 not viewed

        $this->signalRepository
            ->method('countViewedInCategory')
            ->with($student, 5)
            ->willReturn(4); // 4 courses viewed in category → ≥ 3

        $recommendationService = $this->createRecommendationService();

        $boost = $recommendationService->getSignalBoost($student, $course);

        // Not viewed directly → +0, category has 4 views → +2 = 2
        $this->assertEquals(2, $boost);
    }

    // --- Test: both boosts stack ---

    public function testSignalBoostStacksViewAndCategory(): void
    {
        $student = $this->createStudent();

        $category = $this->createMock(\App\Entity\CourseCategory::class);
        $category->method('getId')->willReturn(5);

        $course = $this->createMock(Course::class);
        $course->method('getId')->willReturn(10);
        $course->method('getCategory')->willReturn($category);

        $this->signalRepository
            ->method('findViewedCourseIds')
            ->willReturn([10, 20, 30]); // course 10 viewed

        $this->signalRepository
            ->method('countViewedInCategory')
            ->with($student, 5)
            ->willReturn(5); // 5 courses in category → ≥ 3

        $recommendationService = $this->createRecommendationService();

        $boost = $recommendationService->getSignalBoost($student, $course);

        // Viewed → +3, category pattern → +2 = 5
        $this->assertEquals(5, $boost);
    }

    // --- Test: no boost when no signals ---

    public function testNoBoostWhenNoSignals(): void
    {
        $student = $this->createStudent();

        $course = $this->createMock(Course::class);
        $course->method('getId')->willReturn(99);
        $course->method('getCategory')->willReturn(null);

        $this->signalRepository
            ->method('findViewedCourseIds')
            ->willReturn([]);

        $recommendationService = $this->createRecommendationService();

        $boost = $recommendationService->getSignalBoost($student, $course);

        $this->assertEquals(0, $boost);
    }

    // --- Helper: create a real RecommendationService ---

    private function createRecommendationService(): \App\Service\RecommendationService
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $enrollmentRepo = $this->createMock(\App\Repository\EnrollmentRepository::class);
        $interestRepo = $this->createMock(\App\Repository\InterestProfileRepository::class);

        return new \App\Service\RecommendationService(
            $em,
            $enrollmentRepo,
            $interestRepo,
            $this->signalRepository,
        );
    }
}
