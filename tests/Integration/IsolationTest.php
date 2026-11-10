<?php

namespace App\Tests\Integration;

use App\Entity\Course;
use App\Entity\School;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
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
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        $client = static::createClient();
        $this->em = $client->getContainer()->get('doctrine.orm.entity_manager');
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

    // ── EasyAdmin CRUD isolation ──────────────────────────────────────────────

    /**
     * AdminAislamiento tries to view (detail) a course from Liceo Ficticio.
     */
    public function testAdminCannotViewCourseFromOtherSchool(): void
    {
        $client = static::createClient();

        $liceo = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento = $this->getSchool('colegio-aislamiento');

        $liceoAdmin  = $this->getAdminOf($liceo);        // used as target owner
        $aisAdmin    = $this->getAdminOf($aislamiento);  // attacker
        $liceoCourse = $this->getCourseBelongingTo($liceo);

        $client->loginUser($aisAdmin, 'main');

        $url = '/admin?crudAction=detail'
            . '&crudControllerFqcn=' . urlencode('App\\Controller\\Admin\\CourseCrudController')
            . '&entityId=' . $liceoCourse->getId();

        $client->request('GET', $url);

        $this->assertContains(
            $client->getResponse()->getStatusCode(),
            [403, 302],
            'Admin from other school must not see a course detail from Liceo.'
        );
    }

    /**
     * AdminAislamiento tries to submit an edit form for a Liceo Ficticio course.
     */
    public function testAdminCannotEditCourseFromOtherSchool(): void
    {
        $client = static::createClient();

        $liceo       = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento = $this->getSchool('colegio-aislamiento');
        $aisAdmin    = $this->getAdminOf($aislamiento);
        $liceoCourse = $this->getCourseBelongingTo($liceo);

        $client->loginUser($aisAdmin, 'main');

        $url = '/admin?crudAction=edit'
            . '&crudControllerFqcn=' . urlencode('App\\Controller\\Admin\\CourseCrudController')
            . '&entityId=' . $liceoCourse->getId();

        $client->request('GET', $url);
        $this->assertContains($client->getResponse()->getStatusCode(), [403, 302]);
    }

    /**
     * AdminAislamiento tries to delete a Liceo Ficticio course.
     */
    public function testAdminCannotDeleteCourseFromOtherSchool(): void
    {
        $client = static::createClient();

        $liceo       = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento = $this->getSchool('colegio-aislamiento');
        $aisAdmin    = $this->getAdminOf($aislamiento);
        $liceoCourse = $this->getCourseBelongingTo($liceo);

        $client->loginUser($aisAdmin, 'main');

        $url = '/admin?crudAction=delete'
            . '&crudControllerFqcn=' . urlencode('App\\Controller\\Admin\\CourseCrudController')
            . '&entityId=' . $liceoCourse->getId();

        $client->request('POST', $url);
        $this->assertContains($client->getResponse()->getStatusCode(), [403, 302]);
    }

    /**
     * AdminAislamiento tries to view a user from Liceo Ficticio.
     */
    public function testAdminCannotViewUserFromOtherSchool(): void
    {
        $client = static::createClient();

        $liceo       = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento = $this->getSchool('colegio-aislamiento');
        $aisAdmin    = $this->getAdminOf($aislamiento);
        $liceoUser   = $this->getStudentOf($liceo);

        $client->loginUser($aisAdmin, 'main');

        $url = '/admin?crudAction=detail'
            . '&crudControllerFqcn=' . urlencode('App\\Controller\\Admin\\UserCrudController')
            . '&entityId=' . $liceoUser->getId();

        $client->request('GET', $url);
        $this->assertContains($client->getResponse()->getStatusCode(), [403, 302]);
    }

    /**
     * AdminAislamiento tries to edit a user from Liceo Ficticio.
     */
    public function testAdminCannotEditUserFromOtherSchool(): void
    {
        $client = static::createClient();

        $liceo       = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento = $this->getSchool('colegio-aislamiento');
        $aisAdmin    = $this->getAdminOf($aislamiento);
        $liceoUser   = $this->getStudentOf($liceo);

        $client->loginUser($aisAdmin, 'main');

        $url = '/admin?crudAction=edit'
            . '&crudControllerFqcn=' . urlencode('App\\Controller\\Admin\\UserCrudController')
            . '&entityId=' . $liceoUser->getId();

        $client->request('GET', $url);
        $this->assertContains($client->getResponse()->getStatusCode(), [403, 302]);
    }

    // ── Student enroll isolation ──────────────────────────────────────────────

    /**
     * A student from Colegio Aislamiento cannot enroll in a Liceo Ficticio course.
     */
    public function testStudentCannotEnrollInCourseFromOtherSchool(): void
    {
        $client = static::createClient();

        $liceo       = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento = $this->getSchool('colegio-aislamiento');
        $aisStudent  = $this->getStudentOf($aislamiento);
        $liceoCourse = $this->getCourseBelongingTo($liceo);

        $client->loginUser($aisStudent, 'main');

        $client->request('POST', '/student/enroll/' . $liceoCourse->getId());

        $this->assertContains(
            $client->getResponse()->getStatusCode(),
            [403, 302],
            'Student from other school must receive 403 or redirect on enroll attempt.'
        );

        // Verify no enrollment was created
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $enrollment = $em->getRepository(\App\Entity\Enrollment::class)->findOneBy([
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
        $client = static::createClient();

        $liceo       = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento = $this->getSchool('colegio-aislamiento');
        $aisStudent  = $this->getStudentOf($aislamiento);
        $liceoCourse = $this->getCourseBelongingTo($liceo);

        $client->loginUser($aisStudent, 'main');

        $client->request('POST', '/student/unenroll/' . $liceoCourse->getId());

        $this->assertContains($client->getResponse()->getStatusCode(), [403, 302]);
    }
}
