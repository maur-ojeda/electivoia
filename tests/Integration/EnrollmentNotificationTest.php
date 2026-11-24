<?php

namespace App\Tests\Integration;

use App\Entity\Course;
use App\Entity\CourseCategory;
use App\Entity\Enrollment;
use App\Entity\School;
use App\Entity\User;
use App\Service\EnrollmentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Regression tests for issue #19: enrolling a student sends a confirmation
 * email, and a displaced student is notified when they lose their seat.
 *
 * Uses the usability-seed schools; run app:usability:seed before this suite.
 */
class EnrollmentNotificationTest extends WebTestCase
{
    private const EMAIL_SUFFIX = '%-notify@test.local';
    private const NAME_PREFIX = 'ZZ Notify ';

    private EntityManagerInterface $em;
    /** @var object[] */
    private array $entitiesToDelete = [];

    protected function setUp(): void
    {
        parent::setUp();
        static::ensureKernelShutdown();
        static::createClient();
        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');
        $this->entitiesToDelete = [];
        // Enrollments reference users, so they must go first: a previous
        // interrupted run leaves rows that would block the user purge below.
        $ids = array_column(
            $this->em->createQuery('SELECT u.id FROM App\Entity\User u WHERE u.email LIKE :pattern')
                ->setParameter('pattern', self::EMAIL_SUFFIX)
                ->getScalarResult(),
            'id'
        );
        if ([] !== $ids) {
            $this->em->createQuery('DELETE FROM App\Entity\Enrollment e WHERE e.student IN (:ids)')
                ->setParameter('ids', $ids)
                ->execute();
        }
        $this->em->createQuery('DELETE FROM App\Entity\User u WHERE u.email LIKE :pattern')
            ->setParameter('pattern', self::EMAIL_SUFFIX)
            ->execute();
    }

    protected function tearDown(): void
    {
        // EnrollmentService creates Enrollment rows this test never tracks, so
        // drop every enrollment of our test users before removing the users
        // and courses they point at.
        $ids = array_column(
            $this->em->createQuery('SELECT u.id FROM App\Entity\User u WHERE u.email LIKE :pattern')
                ->setParameter('pattern', self::EMAIL_SUFFIX)
                ->getScalarResult(),
            'id'
        );
        if ([] !== $ids) {
            $this->em->createQuery('DELETE FROM App\Entity\Enrollment e WHERE e.student IN (:ids)')
                ->setParameter('ids', $ids)
                ->execute();
        }

        foreach (array_reverse($this->entitiesToDelete) as $entity) {
            // The displacement path removes the displaced enrollment itself, so
            // its identifier is already gone by the time we get here.
            if (null === $entity->getId()) {
                continue;
            }
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

    public function testEnrollingSendsConfirmationEmailToStudent(): void
    {
        $school = $this->getSchool('liceo-ficticio-electivoia');
        $student = $this->createStudent($school, 'alumno.confirmacion');
        $course = $this->createCourse($school, self::NAME_PREFIX.'Curso Con Cupo', 30);

        $result = $this->service()->enrollStudent($student, $course);

        $this->assertTrue($result['success'], 'The enrollment must succeed when the course has seats.');
        $this->assertEmailCount(1, null, 'Issue #19: enrolling must actually send one confirmation email.');

        $sent = $this->sentEmails();
        $this->assertEmailAddressContains($sent[0], 'To', $student->getEmail());
        $this->assertEmailHeaderSame($sent[0], 'subject', 'Inscripción confirmada: '.$course->getName());
    }

    public function testDisplacingAStudentNotifiesBothStudents(): void
    {
        $school = $this->getSchool('liceo-ficticio-electivoia');

        $low = $this->createStudent($school, 'alumno.desplazado');
        $low->setAverageGrade(4.0);
        $high = $this->createStudent($school, 'alumno.desplazante');
        $high->setAverageGrade(6.5);
        $this->em->flush();

        $course = $this->createCourse($school, self::NAME_PREFIX.'Curso Lleno', 1);
        $this->enrollExisting($low, $course);

        $result = $this->service()->enrollStudent($high, $course);

        $this->assertTrue($result['success'], 'A student with a higher average must take the seat of a full course.');
        $this->assertEmailCount(2, null, 'Issue #19: the displaced student and the new student must both be notified.');

        $sent = $this->sentEmails();
        $this->assertEmailAddressContains($sent[0], 'To', $low->getEmail());
        $this->assertEmailHeaderSame($sent[0], 'subject', 'Has sido desplazado del curso: '.$course->getName());

        $this->assertEmailAddressContains($sent[1], 'To', $high->getEmail());
        $this->assertEmailHeaderSame($sent[1], 'subject', 'Inscripción confirmada: '.$course->getName());
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function service(): EnrollmentService
    {
        return static::getContainer()->get(EnrollmentService::class);
    }

    /**
     * Messages that were actually handed to the transport.
     *
     * With a message bus configured, Mailer records a "queued" event before
     * dispatching and a second "sent" event when the sync transport handles it,
     * so getMailerMessages() returns every email twice.
     *
     * @return list<\Symfony\Component\Mime\RawMessage>
     */
    private function sentEmails(): array
    {
        $events = static::getContainer()
            ->get('mailer.message_logger_listener')
            ->getEvents()
            ->getEvents();

        $sent = [];
        foreach ($events as $event) {
            if (!$event->isQueued()) {
                $sent[] = $event->getMessage();
            }
        }

        return $sent;
    }

    private function createStudent(School $school, string $prefix): User
    {
        $user = new User();
        $user->setEmail($prefix.self::EMAIL_SUFFIX);
        $user->setRoles(['ROLE_STUDENT']);
        $user->setSchool($school);
        $user->setActive(true);
        $user->setGrade('4M');
        $user->setFullName(self::NAME_PREFIX.substr(md5($prefix), 0, 8));
        $n = abs(crc32($prefix)) % 900 + 100;
        $user->setRut("97.900.{$n}-".($n % 9 + 1));

        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get('security.user_password_hasher');
        $user->setPassword($hasher->hashPassword($user, 'Test1234!'));

        $this->em->persist($user);
        $this->em->flush();
        $this->entitiesToDelete[] = $user;

        return $user;
    }

    private function createCourse(School $school, string $name, int $capacity): Course
    {
        $category = $this->em->getRepository(CourseCategory::class)->findOneBy(['name' => 'Artes']);
        $this->assertNotNull($category, 'Seed category "Artes" not found — run app:usability:seed first.');

        $course = new Course();
        $course->setName($name);
        $course->setMaxCapacity($capacity);
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

    /** Places $student in $course without going through EnrollmentService (no email). */
    private function enrollExisting(User $student, Course $course): void
    {
        $enrollment = new Enrollment();
        $enrollment->setStudent($student);
        $enrollment->setCourse($course);
        $enrollment->setEnrolledAt(new \DateTime());
        $course->setCurrentEnrollment($course->getCurrentEnrollment() + 1);

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
