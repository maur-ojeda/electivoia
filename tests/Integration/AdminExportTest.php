<?php

namespace App\Tests\Integration;

use App\Entity\Course;
use App\Entity\CourseCategory;
use App\Entity\Enrollment;
use App\Entity\School;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Regression tests for issue #9: exporting a course's student list to
 * Excel and PDF from the admin panel.
 *
 * Uses the usability-seed schools; run app:usability:seed before this suite.
 */
class AdminExportTest extends WebTestCase
{
    private const EMAIL_SUFFIX = '%-export@test.local';

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    /** @var object[] */
    private array $entitiesToDelete = [];

    protected function setUp(): void
    {
        parent::setUp();
        static::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');
        $this->entitiesToDelete = [];
        $this->em->createQuery('DELETE FROM App\Entity\User u WHERE u.email LIKE :pattern')
            ->setParameter('pattern', self::EMAIL_SUFFIX)
            ->execute();
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->entitiesToDelete) as $entity) {
            $refreshed = $this->em->find($entity::class, $entity->getId());
            if (null !== $refreshed) {
                $this->em->remove($refreshed);
            }
        }
        if ([] !== $this->entitiesToDelete) {
            $this->em->flush();
        }
        $this->entitiesToDelete = [];
        parent::tearDown();
    }

    private function getSchool(string $slug): School
    {
        $school = $this->em->getRepository(School::class)->findOneBy(['slug' => $slug]);
        $this->assertNotNull($school, "Seed school '{$slug}' not found — run app:usability:seed first.");

        return $school;
    }

    private function createAdmin(School $school, string $email): User
    {
        return $this->createUser($school, $email, ['ROLE_ADMIN']);
    }

    private function createStudent(School $school, string $email, string $fullName): User
    {
        $user = $this->createUser($school, $email, ['ROLE_STUDENT']);
        $user->setFullName($fullName);
        $this->em->flush();

        return $user;
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(School $school, string $email, array $roles): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setRoles($roles);
        $user->setSchool($school);
        $user->setActive(true);
        $user->setFullName('Export Test '.substr(md5($email), 0, 8));
        $n = abs(crc32($email)) % 900 + 100;
        $user->setRut("98.900.{$n}-".($n % 9 + 1));

        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get('security.user_password_hasher');
        $user->setPassword($hasher->hashPassword($user, 'Test1234!'));

        $this->em->persist($user);
        $this->em->flush();
        $this->entitiesToDelete[] = $user;

        return $user;
    }

    private function createCourse(School $school, string $name): Course
    {
        $category = $this->em->getRepository(CourseCategory::class)->findOneBy(['name' => 'Artes']);
        $this->assertNotNull($category, 'Seed category "Artes" not found — run app:usability:seed first.');

        $course = new Course();
        $course->setName($name);
        $course->setMaxCapacity(30);
        $course->setCurrentEnrollment(0);
        $course->setIsActive(true);
        $course->setSchool($school);
        $course->setTeacher($this->getTeacherOf($school));
        $course->setCategory($category);
        $course->setTargetGrades(['4M']);

        $this->em->persist($course);
        $this->em->flush();
        $this->entitiesToDelete[] = $course;

        return $course;
    }

    /** Return first ROLE_TEACHER user of $school (course.teacher_id is NOT NULL). */
    private function getTeacherOf(School $school): User
    {
        foreach ($this->em->getRepository(User::class)->findBy(['school' => $school]) as $user) {
            if (in_array('ROLE_TEACHER', $user->getRoles(), true)) {
                return $user;
            }
        }

        $this->fail("No ROLE_TEACHER found for school {$school->getName()}.");
    }

    private function enroll(User $student, Course $course): void
    {
        $enrollment = new Enrollment();
        $enrollment->setStudent($student);
        $enrollment->setCourse($course);

        $this->em->persist($enrollment);
        $this->em->flush();
        $this->entitiesToDelete[] = $enrollment;
    }

    /**
     * StreamedResponse writes to php://output only when sendContent() runs.
     * Grab its callback and execute it inside an output buffer instead.
     */
    private function captureStreamedBody(): string
    {
        $response = $this->client->getResponse();
        $this->assertInstanceOf(
            \Symfony\Component\HttpFoundation\StreamedResponse::class,
            $response,
            'The export must be streamed.',
        );

        $callback = $response->getCallback();
        $this->assertNotNull($callback, 'The streamed export must expose its callback.');

        ob_start();
        $callback();

        return (string) ob_get_clean();
    }

    public function testExcelExportReturnsSpreadsheetWithEnrolledStudents(): void
    {
        $school = $this->getSchool('liceo-ficticio-electivoia');
        $admin = $this->createAdmin($school, 'admin.xlsx-export@test.local');
        $course = $this->createCourse($school, 'Taller de Exportacion XLSX');
        $student = $this->createStudent($school, 'alumno.xlsx-export@test.local', 'Alumno Exportable Xlsx');
        $this->enroll($student, $course);

        $this->client->loginUser($admin, 'main');
        $this->client->request('GET', '/admin/export/course/'.$course->getId().'/students.xlsx');

        $response = $this->client->getResponse();
        $this->assertSame(200, $response->getStatusCode(), 'Excel export must respond 200.');
        $this->assertStringContainsString(
            'spreadsheetml.sheet',
            (string) $response->headers->get('Content-Type'),
            'Excel export must declare a spreadsheet content type.',
        );
        $this->assertStringContainsString(
            'attachment',
            (string) $response->headers->get('Content-Disposition'),
            'Excel export must be served as an attachment.',
        );

        $body = $this->captureStreamedBody();
        $this->assertNotSame('', $body, 'Excel export must produce a body.');
        $this->assertSame("PK\x03\x04", substr($body, 0, 4), 'Excel export must be a valid xlsx (zip) payload.');

        // The xlsx is compressed, so the student name must be read back through
        // the spreadsheet reader instead of grepping the raw bytes.
        $tmpFile = tempnam(sys_get_temp_dir(), 'electivoia-export-');
        $this->assertNotFalse($tmpFile, 'Could not create a scratch file for the xlsx payload.');
        try {
            file_put_contents($tmpFile, $body);
            $spreadsheet = IOFactory::load($tmpFile);
            $values = [];
            foreach ($spreadsheet->getActiveSheet()->toArray() as $row) {
                foreach ($row as $cell) {
                    $values[] = (string) $cell;
                }
            }
            $this->assertContains(
                'Alumno Exportable Xlsx',
                $values,
                'The exported spreadsheet must list the student enrolled in the course.',
            );
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testPdfExportReturnsPdfPayload(): void
    {
        $school = $this->getSchool('liceo-ficticio-electivoia');
        $admin = $this->createAdmin($school, 'admin.pdf-export@test.local');
        $course = $this->createCourse($school, 'Taller de Exportacion PDF');
        $student = $this->createStudent($school, 'alumno.pdf-export@test.local', 'Alumno Exportable Pdf');
        $this->enroll($student, $course);

        $this->client->loginUser($admin, 'main');
        $this->client->request('GET', '/admin/export/course/'.$course->getId().'/students.pdf');

        $response = $this->client->getResponse();
        $this->assertSame(200, $response->getStatusCode(), 'PDF export must respond 200.');
        $this->assertStringContainsString(
            'application/pdf',
            (string) $response->headers->get('Content-Type'),
            'PDF export must declare an application/pdf content type.',
        );

        $body = $this->captureStreamedBody();
        $this->assertNotSame('', $body, 'PDF export must produce a body.');
        $this->assertSame('%PDF', substr($body, 0, 4), 'PDF export must start with the PDF magic bytes.');
    }

    public function testExportWithoutEnrollmentsStillReturnsFile(): void
    {
        $school = $this->getSchool('liceo-ficticio-electivoia');
        $admin = $this->createAdmin($school, 'admin.empty-export@test.local');
        $course = $this->createCourse($school, 'Taller sin Inscritos');

        $this->client->loginUser($admin, 'main');
        $this->client->request('GET', '/admin/export/course/'.$course->getId().'/students.xlsx');
        $this->assertSame(200, $this->client->getResponse()->getStatusCode(),
            'A course with no enrollments must still export an empty spreadsheet, not an error.');
    }
}
