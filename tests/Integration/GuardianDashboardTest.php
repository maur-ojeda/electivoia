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
 * Regression tests for issue #11: a guardian sees the schedule of every course
 * their pupil is enrolled in, not just the course name.
 *
 * Uses the usability-seed schools; run app:usability:seed before this suite.
 */
class GuardianDashboardTest extends WebTestCase
{
    private const EMAIL_SUFFIX = '%-guardian@test.local';
    private const NAME_PREFIX = 'ZZ Guardian ';

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

    public function testGuardianSeesScheduleOfEveryEnrolledCourse(): void
    {
        $school = $this->getSchool('liceo-ficticio-electivoia');

        $pupil = $this->createUser('pupilo'.self::EMAIL_SUFFIX, ['ROLE_STUDENT'], $school);
        $pupil->setGrade('4M');
        $guardian = $this->createUser('apoderado'.self::EMAIL_SUFFIX, ['ROLE_GUARDIAN'], $school);
        $guardian->addGuardianStudent($pupil);
        $this->em->flush();

        $enrolledCourse = $this->createCourse($school, self::NAME_PREFIX.'Curso Matriculado', 'Lun 09:00-10:30');
        $this->enrollStudent($pupil, $enrolledCourse);

        $notEnrolledCourse = $this->createCourse($school, self::NAME_PREFIX.'Curso No Matriculado', 'Mar 11:00-12:30');

        $this->client->loginUser($guardian, 'main');
        $this->client->request('GET', '/guardian/dashboard');

        $this->assertResponseIsSuccessful('The guardian dashboard must be reachable for a guardian.');

        $body = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString($enrolledCourse->getName(), $body, 'The pupil enrolled course must be listed.');
        $this->assertStringContainsString('Lun 09:00-10:30', $body, 'Issue #11: the course schedule must be shown, not only its name.');
        $this->assertStringNotContainsString($notEnrolledCourse->getName(), $body, 'A course the pupil is not enrolled in must not be listed.');
    }

    public function testGuardianWithoutPupilsSeesNoCourseData(): void
    {
        $school = $this->getSchool('liceo-ficticio-electivoia');

        $guardian = $this->createUser('apoderado.solo'.self::EMAIL_SUFFIX, ['ROLE_GUARDIAN'], $school);
        $this->createCourse($school, self::NAME_PREFIX.'Curso De Otro', 'Jue 08:00-09:30');

        $this->client->loginUser($guardian, 'main');
        $this->client->request('GET', '/guardian/dashboard');

        $this->assertResponseIsSuccessful();
        $this->assertStringNotContainsString(
            self::NAME_PREFIX.'Curso De Otro',
            (string) $this->client->getResponse()->getContent(),
            'A guardian with no pupils must not see any course data.'
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
        $user->setRut("97.800.{$n}-".($n % 9 + 1));

        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get('security.user_password_hasher');
        $user->setPassword($hasher->hashPassword($user, 'Test1234!'));

        $this->em->persist($user);
        $this->em->flush();
        $this->entitiesToDelete[] = $user;

        return $user;
    }

    private function createCourse(School $school, string $name, string $schedule): Course
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
        $course->setSchedule($schedule);

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
