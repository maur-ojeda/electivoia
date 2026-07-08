<?php

namespace App\Tests\Unit\Service;

use App\Entity\Course;
use App\Entity\CourseCategory;
use App\Entity\Enrollment;
use App\Entity\InterestProfile;
use App\Entity\School;
use App\Entity\User;
use App\Repository\CourseRepository;
use App\Repository\EnrollmentRepository;
use App\Repository\InterestProfileRepository;
use App\Repository\AiSignalRepository;
use App\Service\RecommendationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class RecommendationServiceCollaborativeTest extends TestCase
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

    private function createPeer(User $peer, array $interests): InterestProfile
    {
        $profile = $this->createMock(InterestProfile::class);
        $profile->method('getStudent')->willReturn($peer);
        $profile->method('getInterests')->willReturn($interests);

        return $profile;
    }

    private function createPeerStudent(array $interests, int $id): User
    {
        $student = $this->createMock(User::class);
        $student->method('getId')->willReturn($id);
        $student->method('getGrade')->willReturn('3B');

        if (!empty($interests)) {
            $profile = $this->createMock(InterestProfile::class);
            $profile->method('getInterests')->willReturn($interests);
            $student->method('getInterestProfile')->willReturn($profile);
        } else {
            $student->method('getInterestProfile')->willReturn(null);
        }

        return $student;
    }

    private function createCourse(
        int $id = 1,
        string $name = 'Curso de prueba',
        ?string $categoryName = 'Ciencias',
        int $maxCapacity = 25,
        int $currentEnrollment = 10,
        ?array $targetGrades = ['3B'],
        bool $isActive = true,
    ): Course {
        $course = $this->createMock(Course::class);
        $course->method('getId')->willReturn($id);
        $course->method('getName')->willReturn($name);
        $course->method('getMaxCapacity')->willReturn($maxCapacity);
        $course->method('getCurrentEnrollment')->willReturn($currentEnrollment);
        $course->method('getTargetGrades')->willReturn($targetGrades);
        $course->method('isActive')->willReturn($isActive);

        if ($categoryName !== null) {
            $category = $this->createMock(CourseCategory::class);
            $category->method('getName')->willReturn($categoryName);
            $course->method('getCategory')->willReturn($category);
        } else {
            $course->method('getCategory')->willReturn(null);
        }

        return $course;
    }

    private function createEnrollment(User $student, Course $course): Enrollment
    {
        $enrollment = $this->createMock(Enrollment::class);
        $enrollment->method('getStudent')->willReturn($student);
        $enrollment->method('getCourse')->willReturn($course);

        return $enrollment;
    }

    private function stubNoProfiles(): void
    {
        $this->interestProfileRepository
            ->method('findOtherProfilesInSchool')
            ->willReturn([]);
    }

    private function stubProfiles(InterestProfile ...$profiles): void
    {
        $this->interestProfileRepository
            ->method('findOtherProfilesInSchool')
            ->willReturn(array_values($profiles));
    }

    private function stubPeerEnrollments(Enrollment ...$enrollments): void
    {
        $this->enrollmentRepository
            ->method('findEnrollmentsByStudents')
            ->willReturn(array_values($enrollments));
    }

    private function stubNotEnrolled(): void
    {
        $this->enrollmentRepository
            ->method('findOneBy')
            ->willReturn(null);
    }

    private function stubEnrolledIn(int ...$courseIds): void
    {
        $this->enrollmentRepository
            ->method('findOneBy')
            ->willReturnCallback(function (array $criteria) use ($courseIds): ?Enrollment {
                $course = $criteria['course'] ?? null;

                if ($course instanceof Course && in_array($course->getId(), $courseIds, true)) {
                    return new Enrollment();
                }

                return null;
            });
    }

    // --- Tests ---

    public function testCoursesEnrolledBySimilarStudentsGetRecommended(): void
    {
        $student = $this->createStudent(['Ciencias' => 5], '3B', 1, 1);

        $peer = $this->createPeerStudent(['Ciencias' => 4], 2);
        $peerProfile = $this->createPeer($peer, ['Ciencias' => 4]);

        $course = $this->createCourse(id: 10, name: 'Biología', categoryName: 'Ciencias');

        $this->stubProfiles($peerProfile);
        $this->stubPeerEnrollments($this->createEnrollment($peer, $course));
        $this->stubNotEnrolled();

        $result = $this->service->getCollaborativeRecommendations($student);

        $this->assertCount(1, $result);
        $this->assertEquals(10, $result[0]['course']->getId());
        // similarity = min(5, 4) = 4
        $this->assertEquals(4, $result[0]['score']);
    }

    public function testDissimilarStudentsCoursesNotRecommended(): void
    {
        $student = $this->createStudent(['Ciencias' => 5], '3B', 1, 1);

        // Peer with completely different interests
        $peer = $this->createPeerStudent(['Deportes' => 4], 2);
        $peerProfile = $this->createPeer($peer, ['Deportes' => 4]);

        $course = $this->createCourse(id: 10, name: 'Fútbol', categoryName: 'Deportes');

        $this->stubProfiles($peerProfile);
        $this->stubPeerEnrollments($this->createEnrollment($peer, $course));
        $this->stubNotEnrolled();

        $result = $this->service->getCollaborativeRecommendations($student);

        // No overlapping categories → similarity 0 → no peers → empty
        $this->assertSame([], $result);
    }

    public function testLowInterestLevelPeerNotRecommended(): void
    {
        $student = $this->createStudent(['Ciencias' => 5], '3B', 1, 1);

        // Peer shares 'Ciencias' but at level 2 (below threshold of 3)
        $peer = $this->createPeerStudent(['Ciencias' => 2], 2);
        $peerProfile = $this->createPeer($peer, ['Ciencias' => 2]);

        $course = $this->createCourse(id: 10, name: 'Biología', categoryName: 'Ciencias');

        $this->stubProfiles($peerProfile);
        $this->stubPeerEnrollments($this->createEnrollment($peer, $course));
        $this->stubNotEnrolled();

        $result = $this->service->getCollaborativeRecommendations($student);

        $this->assertSame([], $result);
    }

    public function testAlreadyEnrolledCoursesExcluded(): void
    {
        $student = $this->createStudent(['Ciencias' => 5], '3B', 1, 1);

        $peer = $this->createPeerStudent(['Ciencias' => 4], 2);
        $peerProfile = $this->createPeer($peer, ['Ciencias' => 4]);

        $course = $this->createCourse(id: 10, name: 'Biología', categoryName: 'Ciencias');

        $this->stubProfiles($peerProfile);
        $this->stubPeerEnrollments($this->createEnrollment($peer, $course));
        $this->stubEnrolledIn(10);

        $result = $this->service->getCollaborativeRecommendations($student);

        $this->assertSame([], $result);
    }

    public function testGradeMismatchExcluded(): void
    {
        $student = $this->createStudent(['Ciencias' => 5], '3B', 1, 1);

        $peer = $this->createPeerStudent(['Ciencias' => 4], 2);
        $peerProfile = $this->createPeer($peer, ['Ciencias' => 4]);

        // Course targets a different grade
        $course = $this->createCourse(
            id: 10,
            name: 'Biología Avanzada',
            categoryName: 'Ciencias',
            targetGrades: ['4B'],
        );

        $this->stubProfiles($peerProfile);
        $this->stubPeerEnrollments($this->createEnrollment($peer, $course));
        $this->stubNotEnrolled();

        $result = $this->service->getCollaborativeRecommendations($student);

        $this->assertSame([], $result);
    }

    public function testHybridMergeCombinesScores(): void
    {
        $student = $this->createStudent(['Ciencias' => 5], '3B', 1, 1);

        $peer = $this->createPeerStudent(['Ciencias' => 4], 2);
        $peerProfile = $this->createPeer($peer, ['Ciencias' => 4]);

        // Course appears in BOTH content-based and collaborative
        $course = $this->createCourse(
            id: 10,
            name: 'Biología',
            categoryName: 'Ciencias',
            maxCapacity: 25,
            currentEnrollment: 10,
            targetGrades: ['3B'],
            isActive: true,
        );

        // Content-based: EM returns the course
        $this->courseRepository
            ->method('findBy')
            ->willReturn([$course]);

        // Collaborative: peer profiles and enrollments
        $this->stubProfiles($peerProfile);
        $this->stubPeerEnrollments($this->createEnrollment($peer, $course));
        $this->stubNotEnrolled();

        $result = $this->service->getHybridRecommendations($student);

        $this->assertCount(1, $result);
        $this->assertEquals(10, $result[0]['course']->getId());

        // Content-based score: 5*10 + 5(cupos) = 55
        // Collaborative score: min(5,4) = 4
        // Combined: 55 + 4 = 59
        $this->assertEquals(59, $result[0]['score']);

        // Reasons should contain both content-based and collaborative reasons
        $reasonsText = implode(' | ', $result[0]['reasons']);
        $this->assertStringContainsString('Coincide con tu interés', $reasonsText);
        $this->assertStringContainsString('intereses similares', $reasonsText);
    }

    public function testEmptyInterestsReturnEmptyArray(): void
    {
        $student = $this->createStudent([], '3B', 1, 1);

        $this->stubNoProfiles();

        $result = $this->service->getCollaborativeRecommendations($student);

        $this->assertSame([], $result);
    }

    public function testResultsAreSortedByScoreDescending(): void
    {
        $student = $this->createStudent(['Ciencias' => 5], '3B', 1, 1);

        // Three peers with different similarity levels
        $peerB = $this->createPeerStudent(['Ciencias' => 4], 2); // similarity = min(5,4) = 4
        $peerC = $this->createPeerStudent(['Ciencias' => 5], 3); // similarity = min(5,5) = 5
        $peerD = $this->createPeerStudent(['Ciencias' => 3], 4); // similarity = min(5,3) = 3

        $courseB = $this->createCourse(id: 10, name: 'Curso B', categoryName: 'Ciencias');
        $courseC = $this->createCourse(id: 20, name: 'Curso C', categoryName: 'Ciencias');
        $courseD = $this->createCourse(id: 30, name: 'Curso D', categoryName: 'Ciencias');

        $this->stubProfiles(
            $this->createPeer($peerB, ['Ciencias' => 4]),
            $this->createPeer($peerC, ['Ciencias' => 5]),
            $this->createPeer($peerD, ['Ciencias' => 3]),
        );

        $this->stubPeerEnrollments(
            $this->createEnrollment($peerB, $courseB),
            $this->createEnrollment($peerC, $courseC),
            $this->createEnrollment($peerD, $courseD),
        );

        $this->stubNotEnrolled();

        $result = $this->service->getCollaborativeRecommendations($student);

        $this->assertCount(3, $result);
        // Highest similarity first
        $this->assertEquals(20, $result[0]['course']->getId()); // score 5
        $this->assertEquals(5, $result[0]['score']);
        $this->assertEquals(10, $result[1]['course']->getId()); // score 4
        $this->assertEquals(4, $result[1]['score']);
        $this->assertEquals(30, $result[2]['course']->getId()); // score 3
        $this->assertEquals(3, $result[2]['score']);
    }

    public function testReasonsContainPeerCount(): void
    {
        $student = $this->createStudent(['Ciencias' => 5], '3B', 1, 1);

        $peerA = $this->createPeerStudent(['Ciencias' => 4], 2);
        $peerB = $this->createPeerStudent(['Ciencias' => 5], 3);

        $course = $this->createCourse(id: 10, name: 'Biología', categoryName: 'Ciencias');

        $this->stubProfiles(
            $this->createPeer($peerA, ['Ciencias' => 4]),
            $this->createPeer($peerB, ['Ciencias' => 5]),
        );

        // Both peers enrolled in the same course
        $this->stubPeerEnrollments(
            $this->createEnrollment($peerA, $course),
            $this->createEnrollment($peerB, $course),
        );

        $this->stubNotEnrolled();

        $result = $this->service->getCollaborativeRecommendations($student);

        $this->assertCount(1, $result);
        // score = min(5,4) + min(5,5) = 4 + 5 = 9
        $this->assertEquals(9, $result[0]['score']);

        $reasonsText = implode(' | ', $result[0]['reasons']);
        $this->assertStringContainsString('2 compañeros con afinidad', $reasonsText);
    }
}
