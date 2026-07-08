<?php

namespace App\Tests\Unit\Service;

use App\Entity\Course;
use App\Entity\CourseCategory;
use App\Entity\Enrollment;
use App\Entity\InterestProfile;
use App\Entity\User;
use App\Repository\CourseRepository;
use App\Repository\EnrollmentRepository;
use App\Repository\InterestProfileRepository;
use App\Service\RecommendationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class RecommendationServiceTest extends TestCase
{
    private RecommendationService $service;
    private \PHPUnit\Framework\MockObject\MockObject $entityManager;
    private \PHPUnit\Framework\MockObject\MockObject $courseRepository;
    private \PHPUnit\Framework\MockObject\MockObject $enrollmentRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->courseRepository = $this->createMock(CourseRepository::class);
        $this->enrollmentRepository = $this->createMock(EnrollmentRepository::class);
        $interestProfileRepository = $this->createMock(InterestProfileRepository::class);

        $this->entityManager
            ->method('getRepository')
            ->willReturn($this->courseRepository);

        $this->service = new RecommendationService(
            $this->entityManager,
            $this->enrollmentRepository,
            $interestProfileRepository,
        );
    }

    private function createStudent(array $interests = [], string $grade = '3B'): User
    {
        $student = $this->createMock(User::class);

        if (!empty($interests)) {
            $profile = $this->createMock(InterestProfile::class);
            $profile->method('getInterests')->willReturn($interests);
            $student->method('getInterestProfile')->willReturn($profile);
        } else {
            $student->method('getInterestProfile')->willReturn(null);
        }

        $student->method('getGrade')->willReturn($grade);

        return $student;
    }

    private function createCourse(
        int $id = 1,
        string $name = 'Curso de prueba',
        ?string $categoryName = 'Ciencias',
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

        if ($categoryName !== null) {
            $category = $this->createMock(CourseCategory::class);
            $category->method('getName')->willReturn($categoryName);
            $course->method('getCategory')->willReturn($category);
        } else {
            $course->method('getCategory')->willReturn(null);
        }

        return $course;
    }

    /**
     * Configura el mock del repositorio de cursos para devolver los cursos indicados.
     */
    private function stubCourses(Course ...$courses): void
    {
        $this->courseRepository
            ->method('findBy')
            ->willReturn(array_values($courses));
    }

    /**
     * Configura el mock para que el estudiante NO esté inscrito en ningún curso.
     */
    private function stubNotEnrolled(): void
    {
        $this->enrollmentRepository
            ->method('findOneBy')
            ->willReturn(null);
    }

    /**
     * Configura el mock para que el estudiante esté inscrito en los cursos con los IDs indicados.
     */
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

    // --- Tests: scoring por nivel de interés ---

    public function testCoursesScoredByInterestLevel(): void
    {
        $student = $this->createStudent(['Ciencias' => 3, 'Arte' => 5], '3B');

        $courseLow = $this->createCourse(id: 1, name: 'Biología', categoryName: 'Ciencias');
        $courseHigh = $this->createCourse(id: 2, name: 'Pintura', categoryName: 'Arte');

        $this->stubCourses($courseLow, $courseHigh);
        $this->stubNotEnrolled();

        $result = $this->service->getForStudentWithReasons($student);

        $this->assertCount(2, $result);

        // Arte (interés 5): 5*10 + 5(cupos) = 55
        // Ciencias (interés 3): 3*10 + 5(cupos) = 35
        $this->assertEquals(2, $result[0]['course']->getId());
        $this->assertEquals(55, $result[0]['score']);
        $this->assertEquals(1, $result[1]['course']->getId());
        $this->assertEquals(35, $result[1]['score']);
    }

    // --- Tests: exclusión por inscripción previa ---

    public function testAlreadyEnrolledCoursesAreExcluded(): void
    {
        $student = $this->createStudent(['Ciencias' => 5], '3B');

        $enrolled = $this->createCourse(id: 1, name: 'Física', categoryName: 'Ciencias');
        $notEnrolled = $this->createCourse(id: 2, name: 'Química', categoryName: 'Ciencias');

        $this->stubCourses($enrolled, $notEnrolled);
        $this->stubEnrolledIn(1);

        $result = $this->service->getForStudentWithReasons($student);

        $this->assertCount(1, $result);
        $this->assertEquals(2, $result[0]['course']->getId());
    }

    // --- Tests: penalización por curso lleno ---

    public function testFullCoursesGetPenalizedButNotExcluded(): void
    {
        $student = $this->createStudent(['Ciencias' => 5], '3B');

        $fullCourse = $this->createCourse(
            id: 1,
            name: 'Física Llena',
            categoryName: 'Ciencias',
            maxCapacity: 25,
            currentEnrollment: 25,
        );

        $this->stubCourses($fullCourse);
        $this->stubNotEnrolled();

        $result = $this->service->getForStudentWithReasons($student);

        $this->assertCount(1, $result);
        // Interés 5 → 50, curso lleno → -10 = 40
        $this->assertEquals(40, $result[0]['score']);
    }

    // --- Tests: bonus por cupos disponibles ---

    public function testCoursesWithAvailableCuposGetBonus(): void
    {
        $student = $this->createStudent(['Ciencias' => 5], '3B');

        $course = $this->createCourse(
            id: 1,
            name: 'Física con cupos',
            categoryName: 'Ciencias',
            maxCapacity: 25,
            currentEnrollment: 10,
        );

        $this->stubCourses($course);
        $this->stubNotEnrolled();

        $result = $this->service->getForStudentWithReasons($student);

        $this->assertCount(1, $result);
        // Interés 5 → 50, cupos disponibles → +5 = 55
        $this->assertEquals(55, $result[0]['score']);
        $this->assertContains('Tiene cupos disponibles', $result[0]['reasons']);
    }

    // --- Tests: filtro por grado ---

    public function testCoursesNotMatchingStudentGradeAreExcluded(): void
    {
        $student = $this->createStudent(['Ciencias' => 5], '3B');

        $wrongGrade = $this->createCourse(
            id: 1,
            name: 'Curso de 4to',
            categoryName: 'Ciencias',
            targetGrades: ['4B'],
        );
        $rightGrade = $this->createCourse(
            id: 2,
            name: 'Curso de 3ro',
            categoryName: 'Ciencias',
            targetGrades: ['3B'],
        );

        $this->stubCourses($wrongGrade, $rightGrade);
        $this->stubNotEnrolled();

        $result = $this->service->getForStudentWithReasons($student);

        $this->assertCount(1, $result);
        $this->assertEquals(2, $result[0]['course']->getId());
    }

    // --- Tests: intereses vacíos ---

    public function testEmptyInterestsReturnEmptyArray(): void
    {
        $student = $this->createStudent([], '3B');

        $course = $this->createCourse(id: 1, categoryName: 'Ciencias');

        $this->stubCourses($course);
        $this->stubNotEnrolled();

        $result = $this->service->getForStudentWithReasons($student);

        $this->assertSame([], $result);
    }

    // --- Tests: ordenamiento descendente ---

    public function testResultsAreSortedByScoreDescending(): void
    {
        $student = $this->createStudent(
            ['Ciencias' => 1, 'Arte' => 3, 'Musica' => 5],
            '3B',
        );

        $course1 = $this->createCourse(id: 1, name: 'A', categoryName: 'Ciencias');
        $course2 = $this->createCourse(id: 2, name: 'B', categoryName: 'Arte');
        $course3 = $this->createCourse(id: 3, name: 'C', categoryName: 'Musica');

        $this->stubCourses($course1, $course2, $course3);
        $this->stubNotEnrolled();

        $result = $this->service->getForStudentWithReasons($student);

        $this->assertCount(3, $result);
        $this->assertEquals(3, $result[0]['course']->getId()); // Musica 5 → 55
        $this->assertEquals(2, $result[1]['course']->getId()); // Arte 3 → 35
        $this->assertEquals(1, $result[2]['course']->getId()); // Ciencias 1 → 15

        // Verifica que los puntajes estén estrictamente en orden descendente
        $this->assertGreaterThanOrEqual($result[1]['score'], $result[0]['score']);
        $this->assertGreaterThanOrEqual($result[2]['score'], $result[1]['score']);
    }

    // --- Tests: límite de resultados ---

    public function testResultsAreLimitedToSix(): void
    {
        $student = $this->createStudent(['Ciencias' => 5], '3B');

        $courses = [];
        for ($i = 1; $i <= 8; ++$i) {
            $courses[] = $this->createCourse(
                id: $i,
                name: "Curso $i",
                categoryName: 'Ciencias',
                maxCapacity: 25,
                currentEnrollment: $i,
            );
        }

        $this->stubCourses(...$courses);
        $this->stubNotEnrolled();

        $result = $this->service->getForStudentWithReasons($student);

        $this->assertCount(6, $result);
    }

    // --- Tests: backward compat de getForStudent ---

    public function testGetForStudentReturnsArrayOfCourseObjects(): void
    {
        $student = $this->createStudent(['Ciencias' => 5], '3B');

        $course1 = $this->createCourse(id: 1, name: 'Física', categoryName: 'Ciencias');
        $course2 = $this->createCourse(id: 2, name: 'Química', categoryName: 'Ciencias');

        $this->stubCourses($course1, $course2);
        $this->stubNotEnrolled();

        $result = $this->service->getForStudent($student);

        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(Course::class, $result);
    }
}
