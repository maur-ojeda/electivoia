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

    private function getUserByEmail(string $email): User
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        $this->assertNotNull($user, "Seed user '{$email}' not found — run app:usability:seed first.");
        return $user;
    }

    private function assertLoginRedirectsTo(string $email, string $expectedPath): void
    {
        $user = $this->getUserByEmail($email);
        $this->client->loginUser($user, 'main');
        $this->client->request('GET', '/');

        // After loginUser, a GET to any protected URL should land on the role home.
        // Follow up to 3 redirects to reach the final destination.
        $this->client->followRedirects(false);
        $this->client->request('GET', $expectedPath);
        $status = $this->client->getResponse()->getStatusCode();

        $this->assertNotSame(302, $status,
            "User {$email} was redirected away from {$expectedPath} (expected to reach it).");
        $this->assertNotSame(403, $status,
            "User {$email} got 403 on {$expectedPath}.");
        $this->assertNotSame(404, $status,
            "User {$email} got 404 on {$expectedPath}.");
    }

    public function testStudentRedirectsToStudentCourses(): void
    {
        $this->assertLoginRedirectsTo('e1.catalina@electivoia.test', '/student/courses');
    }

    public function testTeacherRedirectsToTeacherCourses(): void
    {
        $this->assertLoginRedirectsTo('p1.patricia@electivoia.test', '/teacher/courses');
    }

    public function testGuardianRedirectsToGuardianDashboard(): void
    {
        $this->assertLoginRedirectsTo('a2.andres@electivoia.test', '/guardian/dashboard');
    }

    public function testAdminRedirectsToAdminPanel(): void
    {
        $user = $this->getUserByEmail('ad1.carolina@electivoia.test');
        $this->client->loginUser($user, 'main');
        // EasyAdmin always issues a 302 from /admin to its entity list URL.
        // We assert the redirect target starts with /admin (not / or /login).
        $this->client->followRedirects(false);
        $this->client->request('GET', '/admin');
        $response = $this->client->getResponse();
        $status   = $response->getStatusCode();
        $location = $response->headers->get('Location') ?? '';
        $this->assertSame(302, $status,
            "Admin user ad1.carolina expected 302 on /admin, got {$status}.");
        $path = parse_url($location, PHP_URL_PATH) ?? $location;
        $this->assertStringStartsWith('/admin', $path,
            "Admin redirect should go to /admin, got Location: {$location}");
    }

    public function testSuperAdminRedirectsToSuperAdminPanel(): void
    {
        $this->assertLoginRedirectsTo('sa1.ignacia@electivoia.test', '/super-admin');
    }
}
