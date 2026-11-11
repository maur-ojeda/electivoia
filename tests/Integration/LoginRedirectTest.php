<?php

namespace App\Tests\Integration;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Verifies that each role is redirected to its expected home page after login.
 *
 * Uses usability-seed personas; run app:usability:seed before this suite.
 */
class LoginRedirectTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        static::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');
    }

    private function getUserByUsername(string $username): User
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['username' => $username]);
        $this->assertNotNull($user, "Seed user '{$username}' not found — run app:usability:seed first.");
        return $user;
    }

    private function assertLoginRedirectsTo(string $username, string $expectedPath): void
    {
        $user = $this->getUserByUsername($username);
        $this->client->loginUser($user, 'main');
        $this->client->request('GET', '/');

        // After loginUser, a GET to any protected URL should land on the role home.
        // Follow up to 3 redirects to reach the final destination.
        $this->client->followRedirects(false);
        $this->client->request('GET', $expectedPath);
        $status = $this->client->getResponse()->getStatusCode();

        $this->assertNotSame(302, $status,
            "User {$username} was redirected away from {$expectedPath} (expected to reach it).");
        $this->assertNotSame(403, $status,
            "User {$username} got 403 on {$expectedPath}.");
        $this->assertNotSame(404, $status,
            "User {$username} got 404 on {$expectedPath}.");
    }

    public function testStudentRedirectsToStudentCourses(): void
    {
        $this->assertLoginRedirectsTo('e1.camila', '/student/courses');
    }

    public function testTeacherRedirectsToTeacherCourses(): void
    {
        $this->assertLoginRedirectsTo('p1.martin', '/teacher/courses');
    }

    public function testGuardianRedirectsToGuardianDashboard(): void
    {
        $this->assertLoginRedirectsTo('a2.teresa', '/guardian/dashboard');
    }

    public function testAdminRedirectsToAdminPanel(): void
    {
        $this->assertLoginRedirectsTo('ad1.rodrigo', '/admin');
    }

    public function testSuperAdminRedirectsToSuperAdminPanel(): void
    {
        $this->assertLoginRedirectsTo('sa1.ignacia', '/super-admin');
    }
}
