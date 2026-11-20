<?php

namespace App\Tests\Integration;

use App\Entity\Course;
use App\Entity\CourseCategory;
use App\Entity\InterestProfile;
use App\Entity\School;
use App\Entity\User;
use App\Service\RecommendationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Regression tests for issue #8: a student adjusts their interests and the
 * recommendations react immediately, without any batch job.
 *
 * Uses the usability-seed school and categories; run app:usability:seed first.
 */
class StudentInterestsTest extends WebTestCase
{
    private const EMAIL_SUFFIX = '%-interests@test.local';

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

    private function createStudent(string $email, ?string $grade = '4M'): User
    {
        $school = $this->getSchool('liceo-ficticio-electivoia');

        $user = new User();
        $user->setEmail($email);
        $user->setRoles(['ROLE_STUDENT']);
        $user->setSchool($school);
        $user->setActive(true);
        $user->setFullName('Interests Test '.substr(md5($email), 0, 8));
        $n = abs(crc32($email)) % 900 + 100;
        $user->setRut("98.920.{$n}-".($n % 9 + 1));
        if (null !== $grade) {
            $user->setGrade($grade);
        }

        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get('security.user_password_hasher');
        $user->setPassword($hasher->hashPassword($user, 'Test1234!'));

        $this->em->persist($user);
        $this->em->flush();
        $this->entitiesToDelete[] = $user;

        return $user;
    }

    private function createCourse(string $categoryName, string $name): Course
    {
        $school = $this->getSchool('liceo-ficticio-electivoia');
        $category = $this->em->getRepository(CourseCategory::class)->findOneBy(['name' => $categoryName]);
        $this->assertNotNull($category, "Seed category '{$categoryName}' not found — run app:usability:seed first.");

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

    /**
     * @param array<string, int> $interests
     */
    private function postInterests(User $student, array $interests): void
    {
        $this->client->loginUser($student, 'main');
        $this->client->request('POST', '/student/profile', ['interests' => $interests]);
    }

    public function testProfilePostPersistsInterests(): void
    {
        $student = $this->createStudent('estudiante.persist-interests@test.local');

        $this->postInterests($student, ['Artes' => 5, 'Matemática' => 0]);

        $this->assertSame(302, $this->client->getResponse()->getStatusCode(),
            'Saving the interests form must redirect back to the course list.');

        $this->em->clear();
        $profile = $this->em->getRepository(InterestProfile::class)->findOneBy(['student' => $student->getId()]);
        $this->assertNotNull($profile, 'Saving the form must create the interest profile.');

        $stored = $profile->getInterests();
        $this->assertSame(5, $stored['Artes'], 'The chosen interest level must be persisted.');
        $this->assertSame(0, $stored['Matemática'], 'Unset categories must be stored as zero.');
    }

    public function testProfilePostIgnoresUnknownCategories(): void
    {
        $student = $this->createStudent('estudiante.unknown-interests@test.local');

        $this->postInterests($student, ['Artes' => 3, 'Categoria Inventada' => 9]);

        $this->em->clear();
        $profile = $this->em->getRepository(InterestProfile::class)->findOneBy(['student' => $student->getId()]);
        $this->assertNotNull($profile, 'The profile must be created even with an unknown category present.');

        $stored = $profile->getInterests();
        $this->assertArrayNotHasKey('Categoria Inventada', $stored,
            'Categories outside the allowed list must be discarded, not persisted.');
        $this->assertSame(3, $stored['Artes'], 'Known categories must survive the filtering.');
    }

    public function testProfilePostRequiresStudentGrade(): void
    {
        $student = $this->createStudent('estudiante.nograde-interests@test.local', null);

        $this->postInterests($student, ['Artes' => 4]);

        $this->em->clear();
        $profile = $this->em->getRepository(InterestProfile::class)->findOneBy(['student' => $student->getId()]);
        $this->assertNotNull($profile, 'The profile is stored regardless of grade.');

        /** @var RecommendationService $service */
        $service = static::getContainer()->get(RecommendationService::class);
        $reloaded = $this->em->find(User::class, $student->getId());
        $this->assertInstanceOf(User::class, $reloaded, 'The student must still exist after saving interests.');
        $this->assertSame([], $service->getForStudentWithReasons($reloaded),
            'Without a grade there is no recommendation, which is the documented exclusion rule.');
    }

    public function testInterestsDriveRecommendationScoringImmediately(): void
    {
        $student = $this->createStudent('estudiante.scoring-interests@test.local');
        $artCourse = $this->createCourse('Artes', 'Curso de Arte para Scoring');
        $mathCourse = $this->createCourse('Matemática', 'Curso de Matematica para Scoring');

        $this->postInterests($student, ['Artes' => 5, 'Matemática' => 1]);

        $this->em->clear();
        $reloaded = $this->em->find(User::class, $student->getId());

        /** @var RecommendationService $service */
        $service = static::getContainer()->get(RecommendationService::class);
        $results = $service->getForStudentWithReasons($reloaded);

        $scores = [];
        foreach ($results as $item) {
            $scores[$item['course']->getId()] = $item['score'];
        }

        $this->assertArrayHasKey($artCourse->getId(), $scores,
            'A course matching a chosen interest must appear in the recommendations.');
        $this->assertArrayHasKey($mathCourse->getId(), $scores,
            'A course matching a lower interest must still appear.');

        // INTEREST_MULTIPLIER = 10, AVAILABLE_CUPOS_BONUS = 5.
        $this->assertSame(55, $scores[$artCourse->getId()],
            'Artes level 5 must score 5 * 10 + 5 cupos.');
        $this->assertSame(15, $scores[$mathCourse->getId()],
            'Matemática level 1 must score 1 * 10 + 5 cupos.');

        $artIndex = (int) array_search($artCourse->getId(), array_keys($scores), true);
        $mathIndex = (int) array_search($mathCourse->getId(), array_keys($scores), true);
        $this->assertLessThan(
            $mathIndex,
            $artIndex,
            'The stronger interest must rank first.',
        );
    }
}
