<?php

namespace App\Tests\Integration;

use App\Entity\InterestProfile;
use App\Entity\School;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Regression tests for issue #13: the admin insights report must use the
 * students' interest profiles grouped by grade, not only enrolments per course
 * category.
 *
 * These tests assert the deterministic panel rendered by the page (the AI text
 * itself is mocked in the test environment; the prompt sent to the provider is
 * covered by GeminiInsightsServiceTest).
 *
 * Product rules pinned here:
 *  - "prefiere" means interest level >= 3, the threshold already used by
 *    InterestProfileRepository::countStudentsInterestedInCategory().
 *  - The percentage denominator is the students who actually answered
 *    (at least one level >= 1), not every row in interest_profile: the profile
 *    form persists a row of zeros for a student who answered nothing.
 *  - Only profiles from the admin's school are read, and the grade must be set.
 *
 * Grades 4M / 2M / 1M are used on purpose: the usability seed ships a single
 * profile for a 3M student in school 1, so 3M in that school would contaminate
 * the expected percentages.
 */
class InsightsInterestProfileTest extends WebTestCase
{
    private const EMAIL_SUFFIX = '%-insights@test.local';
    private const NAME_PREFIX = 'ZZ Insights ';

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

        // interest_profile.student_id has no ON DELETE CASCADE, so profiles must
        // be removed before the users they belong to. Also clears leftovers from
        // a crashed run so a stale profile cannot skew a percentage.
        $this->em->getConnection()->executeStatement(
            'DELETE FROM interest_profile WHERE student_id IN (SELECT id FROM "user" WHERE email LIKE :pattern)',
            ['pattern' => self::EMAIL_SUFFIX]
        );
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

    // ── S1: the report shows a percentage per grade ──────────────────────────

    public function testInsightsReportThePreferredCategoryPerGrade(): void
    {
        $school = $this->getSchool('liceo-ficticio-electivoia');

        // 7 of 10 students of 4M prefer Artes (level 4 >= 3).
        for ($i = 1; $i <= 7; ++$i) {
            $this->answer($this->createStudent($school, "artes{$i}", '4M'), [
                'Artes' => 4,
                'Matemática' => 0,
            ]);
        }
        // The other 3 prefer Matemática (level 5) and state a low interest in Artes.
        for ($i = 1; $i <= 3; ++$i) {
            $this->answer($this->createStudent($school, "mate{$i}", '4M'), [
                'Artes' => 1,
                'Matemática' => 5,
            ]);
        }

        $html = $this->renderInsights($school);

        $this->assertStringContainsString(
            '4M · 10 perfiles · Artes 70%, Matemática 30%',
            $html,
            'The report must state what share of the grade prefers each category.'
        );
    }

    // ── S2: unanswered profiles are not part of the denominator ──────────────

    public function testStudentsWhoDidNotAnswerAreExcludedFromThePercentage(): void
    {
        $school = $this->getSchool('liceo-ficticio-electivoia');

        // Two students answered: both prefer Ciencias.
        foreach (['resp-a', 'resp-b'] as $prefix) {
            $this->answer($this->createStudent($school, $prefix, '2M'), [
                'Ciencias' => 4,
                'Artes' => 0,
            ]);
        }
        // Two students left every level at zero: the profile form still stores
        // them, but they declared no preference at all.
        foreach (['blank-a', 'blank-b'] as $prefix) {
            $this->answer($this->createStudent($school, $prefix, '2M'), [
                'Ciencias' => 0,
                'Artes' => 0,
            ]);
        }

        $html = $this->renderInsights($school);

        $this->assertStringContainsString(
            '2M · 2 perfiles · Ciencias 100%',
            $html,
            'Only students who declared at least one interest may count in the denominator.'
        );
        $this->assertStringNotContainsString(
            '2M · 4 perfiles',
            $html,
            'Counting unanswered profiles would halve the percentage and make the insight misleading.'
        );
    }

    // ── S3: a low level is not a preference ──────────────────────────────────

    public function testInterestBelowTheThresholdIsNotCountedAsPreference(): void
    {
        $school = $this->getSchool('liceo-ficticio-electivoia');

        $this->answer($this->createStudent($school, 'strong', '1M'), ['Artes' => 5]);
        $this->answer($this->createStudent($school, 'weak', '1M'), ['Artes' => 1]);

        $html = $this->renderInsights($school);

        $this->assertStringContainsString(
            '1M · 2 perfiles · Artes 50%',
            $html,
            'A declared interest below level 3 must not be counted as a preference.'
        );
    }

    // ── S4: tenant isolation ─────────────────────────────────────────────────

    public function testProfilesFromAnotherSchoolAreNotUsed(): void
    {
        $ownSchool = $this->getSchool('liceo-ficticio-electivoia');
        $otherSchool = $this->getSchool('colegio-aislamiento');

        $this->answer($this->createStudent($ownSchool, 'own', '4M'), ['Filosofía' => 5]);
        // "Astronomía" is not used by the usability seed, so its absence from
        // this admin's report can only mean the other school was filtered out.
        for ($i = 1; $i <= 5; ++$i) {
            $this->answer($this->createStudent($otherSchool, "other{$i}", '4M'), ['Astronomía' => 5]);
        }

        $html = $this->renderInsights($ownSchool);

        $this->assertStringContainsString(
            '4M · 1 perfil · Filosofía 100%',
            $html,
            'The control case: the admin must see the preferences of its own school.'
        );
        $this->assertStringNotContainsString(
            'Astronomía',
            $html,
            'Profiles from another school must never reach this admin report.'
        );
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function renderInsights(School $school): string
    {
        $this->client->loginUser($this->getAdminOf($school), 'main');
        $this->client->request('GET', '/admin/reports/insights');

        $this->assertResponseIsSuccessful('The insights page must be reachable for an admin.');

        return (string) $this->client->getResponse()->getContent();
    }

    private function createStudent(School $school, string $prefix, string $grade): User
    {
        $email = $prefix.self::EMAIL_SUFFIX;

        $user = new User();
        $user->setEmail($email);
        $user->setRoles(['ROLE_STUDENT']);
        $user->setSchool($school);
        $user->setActive(true);
        $user->setGrade($grade);
        $user->setFullName(self::NAME_PREFIX.substr(md5($email), 0, 8));
        $n = abs(crc32($email)) % 900 + 100;
        $user->setRut("97.701.{$n}-".($n % 9 + 1));

        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get('security.user_password_hasher');
        $user->setPassword($hasher->hashPassword($user, 'Test1234!'));

        $this->em->persist($user);
        $this->em->flush();
        $this->entitiesToDelete[] = $user;

        return $user;
    }

    /**
     * @param array<string, int> $interests
     */
    private function answer(User $student, array $interests): InterestProfile
    {
        $profile = new InterestProfile();
        $profile->setStudent($student);
        $profile->setInterests($interests);

        $this->em->persist($profile);
        $this->em->flush();
        $this->entitiesToDelete[] = $profile;

        return $profile;
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
}
