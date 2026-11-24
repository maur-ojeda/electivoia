<?php

namespace App\Tests\Integration;

use App\Entity\Course;
use App\Entity\CourseCategory;
use App\Entity\Enrollment;
use App\Entity\School;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Regression tests for issue #12 (teacher workload), issue #13 (AI insights
 * page) and issue #17 (low-enrollment report view).
 *
 * Uses the usability-seed schools; run app:usability:seed before this suite.
 */
class AdminReportsTest extends WebTestCase
{
    private const EMAIL_SUFFIX = '%-reports@test.local';
    private const NAME_PREFIX = 'ZZ Reports ';

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

    // ── S2 / S6: courses per teacher ─────────────────────────────────────────

    public function testByTeacherGroupsCoursesPerTeacherAndOrdersByWorkload(): void
    {
        $school = $this->getSchool('liceo-ficticio-electivoia');

        $heavy = $this->createUser('prof.heavy'.self::EMAIL_SUFFIX, ['ROLE_TEACHER'], $school);
        $heavy->setFullName(self::NAME_PREFIX.'Profesor Cargado');
        $light = $this->createUser('prof.light'.self::EMAIL_SUFFIX, ['ROLE_TEACHER'], $school);
        $light->setFullName(self::NAME_PREFIX.'Profesor Liviano');
        $this->em->flush();

        $this->createCourse($school, self::NAME_PREFIX.'Curso Cargado A', $heavy);
        $this->createCourse($school, self::NAME_PREFIX.'Curso Cargado B', $heavy);
        $this->createCourse($school, self::NAME_PREFIX.'Curso Liviano', $light);

        $this->client->loginUser($this->getAdminOf($school), 'main');
        $this->client->request('GET', '/admin/reports/by-teacher');

        $this->assertResponseIsSuccessful('The teacher-workload report must be reachable for an admin.');

        $body = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString(self::NAME_PREFIX.'Profesor Cargado', $body, 'Every teacher must appear in the report.');
        $this->assertStringContainsString(self::NAME_PREFIX.'Curso Cargado A', $body, 'The teacher block must list its courses.');
        $this->assertStringContainsString(self::NAME_PREFIX.'Curso Cargado B', $body, 'The teacher block must list all of its courses.');

        $heavyPos = strpos($body, self::NAME_PREFIX.'Profesor Cargado');
        $lightPos = strpos($body, self::NAME_PREFIX.'Profesor Liviano');
        $this->assertNotFalse($heavyPos);
        $this->assertNotFalse($lightPos);
        $this->assertLessThan($lightPos, $heavyPos, 'Teachers must be ordered by course count, heaviest first.');
    }

    public function testByTeacherDoesNotLeakCoursesFromAnotherSchool(): void
    {
        $schoolA = $this->getSchool('liceo-ficticio-electivoia');
        $schoolB = $this->getSchool('colegio-aislamiento');

        $this->createCourse($schoolA, self::NAME_PREFIX.'Curso Propio A', null);
        $foreign = $this->createCourse($schoolB, self::NAME_PREFIX.'Curso Ajeno B', null);

        $this->client->loginUser($this->getAdminOf($schoolA), 'main');
        $this->client->request('GET', '/admin/reports/by-teacher');

        $this->assertResponseIsSuccessful();
        $body = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString(self::NAME_PREFIX.'Curso Propio A', $body, 'Positive control: the own-school course must be listed.');
        $this->assertStringNotContainsString($foreign->getName(), $body, 'An admin must never see another school course in the report.');
    }

    // ── S4 / S6: low-enrollment report ───────────────────────────────────────

    public function testLowEnrollmentListsCoursesUnderThirtyPercentAndSkipsFullOnes(): void
    {
        $school = $this->getSchool('liceo-ficticio-electivoia');

        $empty = $this->createCourse($school, self::NAME_PREFIX.'Curso Vacio', null, 30);
        $full = $this->createCourse($school, self::NAME_PREFIX.'Curso Lleno', null, 2);
        $this->enrollStudent($this->createStudent($school, 'alumno.lleno1'), $full);
        $this->enrollStudent($this->createStudent($school, 'alumno.lleno2'), $full);

        $this->client->loginUser($this->getAdminOf($school), 'main');
        $this->client->request('GET', '/admin/reports/low-enrollment');

        $this->assertResponseIsSuccessful('The low-enrollment report must be reachable for an admin.');
        $body = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString($empty->getName(), $body, 'A course with 0% occupancy must be reported.');
        $this->assertStringNotContainsString($full->getName(), $body, 'A course at 100% occupancy must not be reported.');
    }

    public function testLowEnrollmentDoesNotLeakCoursesFromAnotherSchool(): void
    {
        $schoolA = $this->getSchool('liceo-ficticio-electivoia');
        $schoolB = $this->getSchool('colegio-aislamiento');

        $this->createCourse($schoolA, self::NAME_PREFIX.'Curso Bajo Propio', null, 30);
        $foreign = $this->createCourse($schoolB, self::NAME_PREFIX.'Curso Bajo Ajeno', null, 30);

        $this->client->loginUser($this->getAdminOf($schoolA), 'main');
        $this->client->request('GET', '/admin/reports/low-enrollment');

        $this->assertResponseIsSuccessful();
        $body = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString(self::NAME_PREFIX.'Curso Bajo Propio', $body, 'Positive control: the own-school course must be reported.');
        $this->assertStringNotContainsString($foreign->getName(), $body, 'An admin must never see another school course in the report.');
    }

    // ── S3: insights page renders ────────────────────────────────────────────

    public function testInsightsPageRendersForAdmin(): void
    {
        $school = $this->getSchool('liceo-ficticio-electivoia');

        $this->client->loginUser($this->getAdminOf($school), 'main');
        $this->client->request('GET', '/admin/reports/insights');

        $this->assertResponseIsSuccessful('The AI insights page must be reachable for an admin.');
        $this->assertStringContainsString(
            'Insights Generados por IA',
            (string) $this->client->getResponse()->getContent(),
            'The insights page must render its heading.'
        );
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function createUser(string $email, array $roles, School $school): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setRoles($roles);
        $user->setSchool($school);
        $user->setActive(true);
        $user->setFullName(self::NAME_PREFIX.substr(md5($email), 0, 8));
        $n = abs(crc32($email)) % 900 + 100;
        $user->setRut("97.700.{$n}-".($n % 9 + 1));

        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get('security.user_password_hasher');
        $user->setPassword($hasher->hashPassword($user, 'Test1234!'));

        $this->em->persist($user);
        $this->em->flush();
        $this->entitiesToDelete[] = $user;

        return $user;
    }

    private function createStudent(School $school, string $prefix): User
    {
        $user = $this->createUser($prefix.self::EMAIL_SUFFIX, ['ROLE_STUDENT'], $school);
        $user->setGrade('4M');
        $this->em->flush();

        return $user;
    }

    private function createCourse(School $school, string $name, ?User $teacher, int $capacity = 30): Course
    {
        $category = $this->em->getRepository(CourseCategory::class)->findOneBy(['name' => 'Artes']);
        $this->assertNotNull($category, 'Seed category "Artes" not found — run app:usability:seed first.');

        $course = new Course();
        $course->setName($name);
        $course->setMaxCapacity($capacity);
        $course->setCurrentEnrollment(0);
        $course->setIsActive(true);
        $course->setSchool($school);
        $course->setTeacher($teacher ?? $this->getTeacherOf($school));
        $course->setCategory($category);
        $course->setTargetGrades(['4M']);

        $this->em->persist($course);
        $this->em->flush();
        $this->entitiesToDelete[] = $course;

        return $course;
    }

    private function enrollStudent(User $student, Course $course): void
    {
        $enrollment = new Enrollment();
        $enrollment->setStudent($student);
        $enrollment->setCourse($course);
        $enrollment->setEnrolledAt(new \DateTime());

        $this->em->persist($enrollment);
        $this->em->flush();
        $this->entitiesToDelete[] = $enrollment;
    }

    private function getSchool(string $slug): School
    {
        $school = $this->em->getRepository(School::class)->findOneBy(['slug' => $slug]);
        $this->assertNotNull($school, "Seed school '{$slug}' not found — run app:usability:seed first.");

        return $school;
    }

    private function getAdminOf(School $school): User
    {
        foreach ($this->em->getRepository(User::class)->findBy(['school' => $school]) as $user) {
            if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                return $user;
            }
        }
        $this->fail("No ROLE_ADMIN found for school {$school->getName()}.");
    }

    /** Course.teacher_id is NOT NULL, so every course needs a teacher. */
    private function getTeacherOf(School $school): User
    {
        foreach ($this->em->getRepository(User::class)->findBy(['school' => $school]) as $user) {
            if (in_array('ROLE_TEACHER', $user->getRoles(), true)) {
                return $user;
            }
        }
        $this->fail("No ROLE_TEACHER found for school {$school->getName()}.");
    }
}
