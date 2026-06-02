<?php

namespace App\Tests\Unit\Service;

use App\Entity\Course;
use App\Entity\CourseCategory;
use App\Entity\Enrollment;
use App\Entity\User;
use App\Repository\CourseRepository;
use App\Repository\EnrollmentRepository;
use App\Repository\UserRepository;
use App\Service\EnrollmentService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class EnrollmentServiceTest extends TestCase
{
    private EnrollmentService $service;
    private \PHPUnit\Framework\MockObject\MockObject $entityManager;
    private \PHPUnit\Framework\MockObject\MockObject $courseRepository;
    private \PHPUnit\Framework\MockObject\MockObject $enrollmentRepository;
    private \PHPUnit\Framework\MockObject\MockObject $userRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->courseRepository = $this->createMock(CourseRepository::class);
        $this->enrollmentRepository = $this->createMock(EnrollmentRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);

        $this->service = new EnrollmentService(
            $this->entityManager,
            $this->courseRepository,
            $this->enrollmentRepository,
            $this->userRepository,
        );
    }

    private function createStudent(int $id = 1, string $grade = '3B'): User
    {
        $student = $this->createMock(User::class);
        $student->method('getId')->willReturn($id);
        $student->method('getGrade')->willReturn($grade);
        $student->method('getFullName')->willReturn("Student $id");
        return $student;
    }

    private function createCourse(
        int $id = 1,
        string $name = 'Filosofía Moderna',
        int $maxCapacity = 25,
        int $currentEnrollment = 18,
        bool $isActive = true,
        ?string $schedule = 'Lunes 10:00-11:30',
        ?string $categoryName = 'Filosofía',
        ?User $teacher = null,
    ): Course {
        $course = $this->createMock(Course::class);
        $course->method('getId')->willReturn($id);
        $course->method('getName')->willReturn($name);
        $course->method('getDescription')->willReturn('Exploración de corrientes filosóficas');
        $course->method('getMaxCapacity')->willReturn($maxCapacity);
        $course->method('getCurrentEnrollment')->willReturn($currentEnrollment);
        $course->method('isActive')->willReturn($isActive);
        $course->method('getSchedule')->willReturn($schedule);
        $course->method('getTeacher')->willReturn($teacher);

        $category = null;
        if ($categoryName !== null) {
            $category = $this->createMock(CourseCategory::class);
            $category->method('getName')->willReturn($categoryName);
        }
        $course->method('getCategory')->willReturn($category);

        return $course;
    }

    // --- getAvailableCourses tests ---

    public function testGetAvailableCoursesReturnsCoursesWithCapacity(): void
    {
        $course1 = $this->createCourse(id: 1, name: 'Filosofía Moderna', maxCapacity: 25, currentEnrollment: 18);
        $course2 = $this->createCourse(id: 2, name: 'Artes Visuales', maxCapacity: 20, currentEnrollment: 20);

        $this->courseRepository
            ->method('findAvailableForStudent')
            ->with(grade: null, category: null, search: null)
            ->willReturn([$course1]);

        $result = $this->service->getAvailableCourses();

        $this->assertCount(1, $result['courses']);
        $this->assertEquals(1, $result['total']);

        $course = $result['courses'][0];
        $this->assertEquals(1, $course['id']);
        $this->assertEquals('Filosofía Moderna', $course['name']);
        $this->assertEquals('Filosofía', $course['category']);
        $this->assertEquals('18/25', $course['capacity']);
        $this->assertEquals(7, $course['spots_available']);
    }

    public function testGetAvailableCoursesWithCategoryFilter(): void
    {
        $this->courseRepository
            ->expects($this->once())
            ->method('findAvailableForStudent')
            ->with(grade: null, category: 'Artes', search: null)
            ->willReturn([]);

        $result = $this->service->getAvailableCourses(category: 'Artes');

        $this->assertCount(0, $result['courses']);
        $this->assertEquals(0, $result['total']);
    }

    public function testGetAvailableCoursesWithSearchFilter(): void
    {
        $this->courseRepository
            ->expects($this->once())
            ->method('findAvailableForStudent')
            ->with(grade: null, category: null, search: 'filosofía')
            ->willReturn([]);

        $result = $this->service->getAvailableCourses(search: 'filosofía');

        $this->assertCount(0, $result['courses']);
        $this->assertEquals(0, $result['total']);
    }

    public function testGetAvailableCoursesReturnsEmptyWhenNoCourses(): void
    {
        $this->courseRepository
            ->method('findAvailableForStudent')
            ->willReturn([]);

        $result = $this->service->getAvailableCourses();

        $this->assertCount(0, $result['courses']);
        $this->assertEquals(0, $result['total']);
    }

    public function testGetAvailableCoursesHandlesNullTeacherAndCategory(): void
    {
        $course = $this->createCourse(
            id: 1,
            name: 'Curso Sin Profesor',
            teacher: null,
            categoryName: null,
        );

        $this->courseRepository
            ->method('findAvailableForStudent')
            ->willReturn([$course]);

        $result = $this->service->getAvailableCourses();

        $this->assertEquals('', $result['courses'][0]['teacher']);
        $this->assertNull($result['courses'][0]['category']);
    }

    // --- getCourseDetails tests ---

    public function testGetCourseDetailsReturnsFullDetails(): void
    {
        $student = $this->createStudent();
        $teacher = $this->createMock(User::class);
        $teacher->method('getFullName')->willReturn('Prof. García');
        $course = $this->createCourse(id: 1, teacher: $teacher);

        $this->courseRepository
            ->method('find')
            ->with(1)
            ->willReturn($course);

        $this->enrollmentRepository
            ->method('findOneBy')
            ->with(['student' => $student, 'course' => $course])
            ->willReturn(null);

        $result = $this->service->getCourseDetails(1, $student);

        $this->assertEquals(1, $result['id']);
        $this->assertEquals('Filosofía Moderna', $result['name']);
        $this->assertEquals('Exploración de corrientes filosóficas', $result['description']);
        $this->assertEquals('Filosofía', $result['category']);
        $this->assertEquals('Prof. García', $result['teacher']);
        $this->assertEquals('18/25', $result['capacity']);
        $this->assertEquals(7, $result['spots_available']);
        $this->assertFalse($result['enrolled']);
        $this->assertTrue($result['can_enroll']);
        $this->assertEquals('', $result['enrollment_message']);
    }

    public function testGetCourseDetailsReturnsNotFound(): void
    {
        $student = $this->createStudent();

        $this->courseRepository
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $result = $this->service->getCourseDetails(999, $student);

        $this->assertEquals(0, $result['id']);
        $this->assertEquals('', $result['name']);
        $this->assertFalse($result['can_enroll']);
        $this->assertEquals('Curso no encontrado.', $result['enrollment_message']);
    }

    public function testGetCourseDetailsWhenStudentIsEnrolled(): void
    {
        $student = $this->createStudent();
        $course = $this->createCourse(id: 1);
        $enrollment = new Enrollment();

        $this->courseRepository
            ->method('find')
            ->with(1)
            ->willReturn($course);

        $this->enrollmentRepository
            ->method('findOneBy')
            ->with(['student' => $student, 'course' => $course])
            ->willReturn($enrollment);

        $result = $this->service->getCourseDetails(1, $student);

        $this->assertTrue($result['enrolled']);
        $this->assertFalse($result['can_enroll']);
        $this->assertEquals('Ya estás inscrito en este curso.', $result['enrollment_message']);
    }

    public function testGetCourseDetailsWhenCourseIsInactive(): void
    {
        $student = $this->createStudent();
        $course = $this->createCourse(id: 1, isActive: false);

        $this->courseRepository
            ->method('find')
            ->with(1)
            ->willReturn($course);

        $this->enrollmentRepository
            ->method('findOneBy')
            ->willReturn(null);

        $result = $this->service->getCourseDetails(1, $student);

        $this->assertFalse($result['can_enroll']);
        $this->assertEquals('El curso no está disponible.', $result['enrollment_message']);
    }

    public function testGetCourseDetailsWhenNoSpotsAvailable(): void
    {
        $student = $this->createStudent();
        $course = $this->createCourse(id: 1, maxCapacity: 25, currentEnrollment: 25);

        $this->courseRepository
            ->method('find')
            ->with(1)
            ->willReturn($course);

        $this->enrollmentRepository
            ->method('findOneBy')
            ->willReturn(null);

        $result = $this->service->getCourseDetails(1, $student);

        $this->assertEquals(0, $result['spots_available']);
        $this->assertFalse($result['can_enroll']);
        $this->assertEquals('No hay cupos disponibles.', $result['enrollment_message']);
    }

    // --- isEnrolled tests ---

    public function testIsEnrolledReturnsTrueWhenEnrolled(): void
    {
        $student = $this->createStudent();
        $course = $this->createCourse();
        $enrollment = new Enrollment();

        $this->enrollmentRepository
            ->method('findOneBy')
            ->with(['student' => $student, 'course' => $course])
            ->willReturn($enrollment);

        $this->assertTrue($this->service->isEnrolled($student, $course));
    }

    public function testIsEnrolledReturnsFalseWhenNotEnrolled(): void
    {
        $student = $this->createStudent();
        $course = $this->createCourse();

        $this->enrollmentRepository
            ->method('findOneBy')
            ->with(['student' => $student, 'course' => $course])
            ->willReturn(null);

        $this->assertFalse($this->service->isEnrolled($student, $course));
    }
}
