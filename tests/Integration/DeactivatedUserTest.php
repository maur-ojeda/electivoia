<?php

namespace App\Tests\Integration;

use App\Entity\School;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Regression tests for issue #10: deactivating a user must remove their access.
 *
 * User::getRoles() returns an empty array when $active is false, so the
 * authenticated token carries no role and access_control denies every
 * protected area. These tests pin that behaviour end to end.
 *
 * Uses the usability-seed school; run app:usability:seed before this suite.
 */
class DeactivatedUserTest extends WebTestCase
{
    private const EMAIL_SUFFIX = '%-deact@test.local';

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

    /**
     * @param list<string> $roles
     */
    private function createUser(string $email, array $roles, bool $active): User
    {
        $school = $this->getSchool('liceo-ficticio-electivoia');

        $user = new User();
        $user->setEmail($email);
        $user->setRoles($roles);
        $user->setSchool($school);
        $user->setActive($active);
        $user->setFullName('Deact Test '.substr(md5($email), 0, 8));
        $n = abs(crc32($email)) % 900 + 100;
        $user->setRut("98.910.{$n}-".($n % 9 + 1));

        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get('security.user_password_hasher');
        $user->setPassword($hasher->hashPassword($user, 'Test1234!'));

        $this->em->persist($user);
        $this->em->flush();
        $this->entitiesToDelete[] = $user;

        return $user;
    }

    public function testDeactivatedUserHasNoRoles(): void
    {
        $user = $this->createUser('inactivo.roles-deact@test.local', ['ROLE_ADMIN'], false);

        $this->assertSame([], $user->getRoles(),
            'A deactivated user must expose no roles, not even ROLE_USER.');
        $this->assertNotContains('ROLE_ADMIN', $user->getRoles(),
            'A deactivated admin must lose ROLE_ADMIN.');
    }

    public function testActiveUserKeepsRoles(): void
    {
        $user = $this->createUser('activo.roles-deact@test.local', ['ROLE_ADMIN'], true);

        $this->assertContains('ROLE_ADMIN', $user->getRoles(), 'An active admin keeps ROLE_ADMIN.');
        $this->assertContains('ROLE_USER', $user->getRoles(), 'An active user gets ROLE_USER.');
    }

    public function testDeactivatedAdminCannotReachAdminPanel(): void
    {
        $admin = $this->createUser('inactivo.admin-deact@test.local', ['ROLE_ADMIN'], false);

        $this->client->loginUser($admin, 'main');
        $this->client->request('GET', '/admin');

        $this->assertSame(403, $this->client->getResponse()->getStatusCode(),
            'A deactivated admin must be denied access to /admin.');
    }

    public function testDeactivatedStudentCannotReachStudentArea(): void
    {
        $student = $this->createUser('inactivo.estudiante-deact@test.local', ['ROLE_STUDENT'], false);

        $this->client->loginUser($student, 'main');
        $this->client->request('GET', '/student/courses');

        $this->assertSame(403, $this->client->getResponse()->getStatusCode(),
            'A deactivated student must be denied access to /student.');
    }

    public function testDeactivatedStudentCannotReachProfile(): void
    {
        $student = $this->createUser('inactivo.perfil-deact@test.local', ['ROLE_STUDENT'], false);

        $this->client->loginUser($student, 'main');
        $this->client->request('GET', '/student/profile');

        $this->assertSame(403, $this->client->getResponse()->getStatusCode(),
            'A deactivated student must not reach the interests form either.');
    }

    public function testActiveAdminStillReachesAdminPanel(): void
    {
        $admin = $this->createUser('activo.admin-deact@test.local', ['ROLE_ADMIN'], true);

        $this->client->loginUser($admin, 'main');
        $this->client->request('GET', '/admin');

        // EasyAdmin answers /admin with a 302 to its entity list.
        $this->assertNotSame(403, $this->client->getResponse()->getStatusCode(),
            'An active admin must not be denied access to /admin.');
        $this->assertNotSame(404, $this->client->getResponse()->getStatusCode(),
            'The admin panel must exist for an active admin.');
    }
}
