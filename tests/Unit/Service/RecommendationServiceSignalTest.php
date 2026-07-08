<?php

namespace App\Tests\Unit\Service;

use App\Entity\Course;
use App\Entity\CourseCategory;
use App\Entity\Enrollment;
use App\Entity\InterestProfile;
use App\Entity\School;
use App\Entity\User;
use App\Repository\AiSignalRepository;
use App\Repository\CourseRepository;
use App\Repository\EnrollmentRepository;
use App\Repository\InterestProfileRepository;
use App\Service\RecommendationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests signal boost integration in RecommendationService::getHybridRecommendations.
 */
class RecommendationServiceSignalTest extends TestCase
{
    private RecommendationService $service;
    private \PHPUnit\Framework\MockObject\MockObject $entityManager;
    private \PHPUnit\Framework\MockObject\MockObject $courseRepository;
    private \PHPUnit\Framework\MockObject\MockObject $enrollmentRepository;
    private \PHPUnit\Framework\MockObject\MockObject $interestProfileRepository;
    private \PHPUnit\Framework\MockObject\MockObject $signalRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->courseRepository = $this->createMock(CourseRepository::class);
        $this->enrollmentRepository = $this->createMock(EnrollmentRepository::class);
        $this->interestProfileRepository = $this->createMock(InterestProfileRepository::class);
        $this->signalRepository = $this->createMock(AiSignalRepository::class);

        $this->entityManager
            ->method('getRepository')
            ->willReturn($this->courseRepository);

        // Default: no signals
        $this->signalRepository->method('findViewedCourseIds')->willReturn([]);
        $this->signalRepository->method('countViewedInCategory')->willReturn(0);

        $this->service = new RecommendationService(
            $this->entityManager,
            $this->enrollmentRepository,
            $this->interestProfileRepository,
            $this->signalRepository,
        );
    }

    // --- Helpers ---

    private function createStudent(
        array $interests = [],
        string $grade = '3B',
        ?int $schoolId = 1,
        ?int $id = 1,
    ): User {
        $student = $this->createMock(User::class);
        $student->method('getId')->willReturn($id);
        $student->method('getGrade')->willReturn($grade);

        if (!empty($interests)) {
            $profile = $this->createMock(InterestProfile::class);
            $profile->method('getInterests')->willReturn($interests);
            $student->method('getInterestProfile')->willReturn($profile);
        } else {
            $student->method('getInterestProfile')->willReturn(null);
        }

        if ($schoolId !== null) {
            $school = $this->createMock(School::class);
            $school->method('getId')->willReturn($schoolId);
            $student->method('getSchool')->willReturn($school);
        } else {
            $student->method('getSchool')->willReturn(null);
        }

        return $student;
    }

    private function createCourse(
        int $id = 1,
        string $name = 'Curso de prueba',
        ?int $categoryId = 1,
        string $categoryName = 'Ciencias',
        int $maxCapacity = 25,
        int $currentEnrollment = 10,
        ?array $targetGrades = ['3B'],
    ): Course {
        $course = $this->createMock(Course::class);
        $course->method('getId')->willReturn($id);
        $course->method('getName')->willReturn($name);
        $course->method('getMaxCapacity')->willReturn($maxCapacity);
        $course->method('getCurrentEnrollment')->willReturn($currentEnrollment);
        $course->method('getTargetGrades')->willReturn($targetGrades);

        if ($categoryId !== null) {
            $category = $this->createMock(CourseCategory::class);
            $category->method('getId')->willReturn($categoryId);
            $category->method('getName')->willReturn($categoryName);
            $course->method('getCategory')->willReturn($category);
        } else {
            $course->method('getCategory')->willReturn(null);
        }

        return $course;
    }

    // --- Tests ---

    /**
     * Hybrid recommendations should include signal boost when the student
     * previously viewed the recommended course.
     */
    public function testHybridIncludesSignalBoostForViewedCourse(): void
    {
        $student = $this->createStudent(['Ciencias' => 5], '3B', 1, 1);

        $course = $this->createCourse(
            id: 10,
            name: 'Biologia',
            categoryId: 1,
            categoryName: 'Ciencias',
            maxCapacity: 25,
            currentEnrollment: 10,
            targetGrades: ['3B'],
        );

        // Content-based returns the course
        $this->courseRepository
            ->method('findBy')
            ->willReturn([$course]);

        // No peers → collaborative returns empty
        $this->interestProfileRepository
            ->method('findOtherProfilesInSchool')
            ->willReturn([]);

        $this->enrollmentRepository->method('findOneBy')->willReturn(null);

        // Signal: student viewed course 10
        $this->signalRepository = $this->createMock(AiSignalRepository::class);
        $this->signalRepository->method('findViewedCourseIds')->willReturn([10]);
        $this->signalRepository->method('countViewedInCategory')->willReturn(0);

        $this->service = new RecommendationService(
            $this->entityManager,
            $this->enrollmentRepository,
            $this->interestProfileRepository,
            $this->signalRepository,
        );

        $result = $this->service->getHybridRecommendations($student);

        $this->assertCount(1, $result);

        // Content-based score: 5*10 + 5(cupos) = 55
        // Signal boost: +3 (viewed before)
        // Total: 55 + 3 = 58
        $this->assertEquals(58, $result[0]['score']);

        // Reason should mention previous view
        $reasonsText = implode(' | ', $result[0]['reasons']);
        $this->assertStringContainsString('Viste este curso anteriormente', $reasonsText);
    }

    /**
     * Signal boost should NOT apply when the student hasn't viewed the course.
     */
    public function testHybridWithoutSignalBoostWhenNotViewed(): void
    {
        $student = $this->createStudent(['Ciencias' => 5], '3B', 1, 1);

        $course = $this->createCourse(
            id: 10,
            name: 'Biologia',
            categoryId: 1,
            categoryName: 'Ciencias',
        );

        $this->courseRepository
            ->method('findBy')
            ->willReturn([$course]);

        $this->interestProfileRepository
            ->method('findOtherProfilesInSchool')
            ->willReturn([]);

        $this->enrollmentRepository->method('findOneBy')->willReturn(null);

        // Student has NOT viewed course 10
        $this->signalRepository = $this->createMock(AiSignalRepository::class);
        $this->signalRepository->method('findViewedCourseIds')->willReturn([20, 30]);
        $this->signalRepository->method('countViewedInCategory')->willReturn(0);

        $this->service = new RecommendationService(
            $this->entityManager,
            $this->enrollmentRepository,
            $this->interestProfileRepository,
            $this->signalRepository,
        );

        $result = $this->service->getHybridRecommendations($student);

        $this->assertCount(1, $result);

        // Content-based: 5*10 + 5 = 55, no boost = 55
        $this->assertEquals(55, $result[0]['score']);

        // No "Viste este curso anteriormente" reason
        $reasonsText = implode(' | ', $result[0]['reasons']);
        $this->assertStringNotContainsString('Viste este curso anteriormente', $reasonsText);
    }

    /**
     * Signal boost should add category pattern bonus (+2) when student has
     * viewed 3+ courses in the same category.
     */
    public function testHybridIncludesCategoryPatternBoost(): void
    {
        $student = $this->createStudent(['Ciencias' => 5], '3B', 1, 1);

        $category = $this->createMock(CourseCategory::class);
        $category->method('getId')->willReturn(1);
        $category->method('getName')->willReturn('Ciencias');

        $course = $this->createMock(Course::class);
        $course->method('getId')->willReturn(10);
        $course->method('getName')->willReturn('Biologia');
        $course->method('getMaxCapacity')->willReturn(25);
        $course->method('getCurrentEnrollment')->willReturn(10);
        $course->method('getTargetGrades')->willReturn(['3B']);
        $course->method('getCategory')->willReturn($category);

        $this->courseRepository
            ->method('findBy')
            ->willReturn([$course]);

        $this->interestProfileRepository
            ->method('findOtherProfilesInSchool')
            ->willReturn([]);

        $this->enrollmentRepository->method('findOneBy')->willReturn(null);

        // Student viewed 4 courses in category 1, but NOT course 10 itself
        $this->signalRepository = $this->createMock(AiSignalRepository::class);
        $this->signalRepository->method('findViewedCourseIds')->willReturn([20, 30, 40, 50]);
        $this->signalRepository->method('countViewedInCategory')->willReturn(4);

        $this->service = new RecommendationService(
            $this->entityManager,
            $this->enrollmentRepository,
            $this->interestProfileRepository,
            $this->signalRepository,
        );

        $result = $this->service->getHybridRecommendations($student);

        $this->assertCount(1, $result);

        // Content-based: 5*10 + 5 = 55
        // Not viewed directly → +0
        // Category pattern (4 ≥ 3) → +2
        // Total: 55 + 2 = 57
        $this->assertEquals(57, $result[0]['score']);
    }
}
