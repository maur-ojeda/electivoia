<?php

namespace App\Tests\Integration;

use App\Entity\Course;
use App\Entity\School;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Verifies school-isolation: a user from school A cannot view, edit, delete,
 * or enroll in entities belonging to school B.
 *
 * Uses the two usability-seed schools (slugs liceo-ficticio-electivoia and
 * colegio-aislamiento).  The seed must be running before this suite executes.
 */
class IsolationTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        static::ensureKernelShutdown();
        // Boot kernel once per test; reuse client across assertions within a test.
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function getSchool(string $slug): School
    {
        $school = $this->em->getRepository(School::class)->findOneBy(['slug' => $slug]);
        $this->assertNotNull($school, "Seed school '{$slug}' not found — run app:usability:seed first.");
        return $school;
    }

    /** Return first active course belonging to $school. */
    private function getCourseBelongingTo(School $school): Course
    {
        $course = $this->em->getRepository(Course::class)->findOneBy(['school' => $school]);
        $this->assertNotNull($course, "No course found for school {$school->getName()}.");
        return $course;
    }

    /** Return first ROLE_ADMIN user of $school. */
    private function getAdminOf(School $school): User
    {
        $users = $this->em->getRepository(User::class)->findBy(['school' => $school]);
        foreach ($users as $user) {
            if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                return $user;
            }
        }
        $this->fail("No ROLE_ADMIN found for school {$school->getName()}.");
    }

    /** Return first ROLE_STUDENT user of $school. */
    private function getStudentOf(School $school): User
    {
        $users = $this->em->getRepository(User::class)->findBy(['school' => $school]);
        foreach ($users as $user) {
            if (in_array('ROLE_STUDENT', $user->getRoles(), true)) {
                return $user;
            }
        }
        $this->fail("No ROLE_STUDENT found for school {$school->getName()}.");
    }

    // ── Old URL format ────────────────────────────────────────────────────────

    /**
     * The legacy ?crudAction= query-param URL format must return 404, not 500.
     */
    public function testLegacyUrlFormatReturns404(): void
    {
        $aislamiento = $this->getSchool('colegio-aislamiento');
        $aisAdmin    = $this->getAdminOf($aislamiento);
        $this->client->loginUser($aisAdmin, 'main');

        $url = '/admin?crudAction=edit'
            . '&crudControllerFqcn=' . urlencode('App\\Controller\\Admin\\CourseCrudController')
            . '&entityId=1';

        $this->client->request('GET', $url);
        $this->assertSame(404, $this->client->getResponse()->getStatusCode(),
            'Legacy EasyAdmin URL format must return 404, not 500.');
    }

    // ── EasyAdmin CRUD isolation ──────────────────────────────────────────────

    /**
     * AdminAislamiento tries to view (detail) a course from Liceo Ficticio.
     * Expects 403 (voter blocks) or 404 (entity filtered from scope).
     */
    public function testAdminCannotViewCourseFromOtherSchool(): void
    {
        $liceo       = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento = $this->getSchool('colegio-aislamiento');
        $aisAdmin    = $this->getAdminOf($aislamiento);
        $liceoCourse = $this->getCourseBelongingTo($liceo);

        $this->client->loginUser($aisAdmin, 'main');
        $this->client->request('GET', '/admin/course/' . $liceoCourse->getId() . '/detail');

        $this->assertContains(
            $this->client->getResponse()->getStatusCode(),
            [403, 404, 302],
            'Admin from other school must not see a course detail from Liceo.'
        );
    }

    /**
     * AdminAislamiento tries to submit an edit form for a Liceo Ficticio course.
     */
    public function testAdminCannotEditCourseFromOtherSchool(): void
    {
        $liceo       = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento = $this->getSchool('colegio-aislamiento');
        $aisAdmin    = $this->getAdminOf($aislamiento);
        $liceoCourse = $this->getCourseBelongingTo($liceo);

        $this->client->loginUser($aisAdmin, 'main');
        $this->client->request('GET', '/admin/course/' . $liceoCourse->getId() . '/edit');
        $this->assertContains($this->client->getResponse()->getStatusCode(), [403, 404, 302]);
    }

    /**
     * AdminAislamiento tries to delete a Liceo Ficticio course.
     */
    public function testAdminCannotDeleteCourseFromOtherSchool(): void
    {
        $liceo       = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento = $this->getSchool('colegio-aislamiento');
        $aisAdmin    = $this->getAdminOf($aislamiento);
        $liceoCourse = $this->getCourseBelongingTo($liceo);

        $this->client->loginUser($aisAdmin, 'main');
        $this->client->request('POST', '/admin/course/' . $liceoCourse->getId() . '/delete');
        $this->assertContains($this->client->getResponse()->getStatusCode(), [403, 404, 302]);
    }

    /**
     * AdminAislamiento tries to view a user from Liceo Ficticio.
     */
    public function testAdminCannotViewUserFromOtherSchool(): void
    {
        $liceo       = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento = $this->getSchool('colegio-aislamiento');
        $aisAdmin    = $this->getAdminOf($aislamiento);
        $liceoUser   = $this->getStudentOf($liceo);

        $this->client->loginUser($aisAdmin, 'main');
        $this->client->request('GET', '/admin/user/' . $liceoUser->getId() . '/detail');
        $this->assertContains($this->client->getResponse()->getStatusCode(), [403, 404, 302]);
    }

    /**
     * AdminAislamiento tries to edit a user from Liceo Ficticio.
     */
    public function testAdminCannotEditUserFromOtherSchool(): void
    {
        $liceo       = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento = $this->getSchool('colegio-aislamiento');
        $aisAdmin    = $this->getAdminOf($aislamiento);
        $liceoUser   = $this->getStudentOf($liceo);

        $this->client->loginUser($aisAdmin, 'main');
        $this->client->request('GET', '/admin/user/' . $liceoUser->getId() . '/edit');
        $this->assertContains($this->client->getResponse()->getStatusCode(), [403, 404, 302]);
    }

    // ── Student enroll isolation ──────────────────────────────────────────────

    /**
     * A student from Colegio Aislamiento cannot enroll in a Liceo Ficticio course.
     */
    public function testStudentCannotEnrollInCourseFromOtherSchool(): void
    {
        $liceo       = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento = $this->getSchool('colegio-aislamiento');
        $aisStudent  = $this->getStudentOf($aislamiento);
        $liceoCourse = $this->getCourseBelongingTo($liceo);

        $this->client->loginUser($aisStudent, 'main');
        $this->client->request('POST', '/student/enroll/' . $liceoCourse->getId());

        $this->assertContains(
            $this->client->getResponse()->getStatusCode(),
            [403, 302],
            'Student from other school must receive 403 or redirect on enroll attempt.'
        );

        // Verify no enrollment was created
        $enrollment = $this->em->getRepository(\App\Entity\Enrollment::class)->findOneBy([
            'student' => $aisStudent,
            'course'  => $liceoCourse,
        ]);
        $this->assertNull($enrollment, 'No enrollment must be created for cross-school attempt.');
    }

    /**
     * A student from Colegio Aislamiento cannot unenroll from a Liceo course.
     */
    public function testStudentCannotUnenrollFromCourseOfOtherSchool(): void
    {
        $liceo       = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento = $this->getSchool('colegio-aislamiento');
        $aisStudent  = $this->getStudentOf($aislamiento);
        $liceoCourse = $this->getCourseBelongingTo($liceo);

        $this->client->loginUser($aisStudent, 'main');
        $this->client->request('POST', '/student/unenroll/' . $liceoCourse->getId());

        $this->assertContains($this->client->getResponse()->getStatusCode(), [403, 302]);
    }
}
