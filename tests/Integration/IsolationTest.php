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

    /** Return first ROLE_TEACHER user of $school. */
    private function getTeacherOf(School $school): User
    {
        $users = $this->em->getRepository(User::class)->findBy(['school' => $school]);
        foreach ($users as $user) {
            if (in_array('ROLE_TEACHER', $user->getRoles(), true)) {
                return $user;
            }
        }
        $this->fail("No ROLE_TEACHER found for school {$school->getName()}.");
    }

    /** Return first ROLE_GUARDIAN user of $school. */
    private function getGuardianOf(School $school): User
    {
        $users = $this->em->getRepository(User::class)->findBy(['school' => $school]);
        foreach ($users as $user) {
            if (in_array('ROLE_GUARDIAN', $user->getRoles(), true)) {
                return $user;
            }
        }
        $this->fail("No ROLE_GUARDIAN found for school {$school->getName()}.");
    }

    /** Return first active course owned by $teacher. */
    private function getCourseOwnedBy(User $teacher): ?\App\Entity\Course
    {
        return $this->em->getRepository(\App\Entity\Course::class)->findOneBy(['teacher' => $teacher]);
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

    // ── Teacher isolation ─────────────────────────────────────────────────────

    /**
     * A teacher from Colegio Aislamiento cannot view the attendance page
     * of a course that belongs to another teacher in the same school
     * (owner check), nor of a cross-school course (SAME_SCHOOL voter).
     */
    public function testTeacherCannotViewAttendanceOfCourseFromOtherSchool(): void
    {
        $liceo       = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento = $this->getSchool('colegio-aislamiento');
        $aisTeacher  = $this->getTeacherOf($aislamiento);
        $liceoCourse = $this->getCourseBelongingTo($liceo);

        $this->client->loginUser($aisTeacher, 'main');
        $this->client->request('GET', '/teacher/attendance/' . $liceoCourse->getId());

        $this->assertContains(
            $this->client->getResponse()->getStatusCode(),
            [403, 404, 302],
            'Teacher must not access attendance of a course from another school.'
        );
    }

    /**
     * A teacher from Colegio Aislamiento cannot view the attendance report
     * of a course from Liceo Ficticio.
     */
    public function testTeacherCannotViewReportOfCourseFromOtherSchool(): void
    {
        $liceo       = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento = $this->getSchool('colegio-aislamiento');
        $aisTeacher  = $this->getTeacherOf($aislamiento);
        $liceoCourse = $this->getCourseBelongingTo($liceo);

        $this->client->loginUser($aisTeacher, 'main');
        $this->client->request('GET', '/teacher/report/' . $liceoCourse->getId());

        $this->assertContains($this->client->getResponse()->getStatusCode(), [403, 404, 302]);
    }

    /**
     * A teacher from Liceo Ficticio cannot view the attendance page of a
     * course that belongs to a *colleague* in the same school.
     * (Owner check, not school voter — but must still return 403.)
     */
    public function testTeacherCannotViewAttendanceOfColleagueCourse(): void
    {
        $liceo          = $this->getSchool('liceo-ficticio-electivoia');
        $liceoTeacher1  = $this->getTeacherOf($liceo);
        $liceoTeacher2  = $this->getSecondTeacherOf($liceo, $liceoTeacher1);

        if ($liceoTeacher2 === null) {
            $this->markTestSkipped('Liceo has only one teacher in the seed — cannot test colleague isolation.');
        }

        $teacher1Course = $this->getCourseOwnedBy($liceoTeacher1);
        if ($teacher1Course === null) {
            $this->markTestSkipped('Teacher 1 has no course in seed data.');
        }

        $this->client->loginUser($liceoTeacher2, 'main');
        $this->client->request('GET', '/teacher/attendance/' . $teacher1Course->getId());

        $this->assertContains(
            $this->client->getResponse()->getStatusCode(),
            [403, 404, 302],
            'Teacher must not access attendance of a colleague\'s course.'
        );
    }

    // ── Guardian isolation ────────────────────────────────────────────────────

    /**
     * A guardian from Colegio Aislamiento sees only their own students on the
     * dashboard; they must not see students from Liceo Ficticio.
     * (Guardians access students via direct relationship, not school scoping,
     * so a cross-school student linked to a different guardian is unreachable.)
     */
    public function testGuardianDashboardDoesNotLeakStudentsFromOtherSchool(): void
    {
        $liceo       = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento = $this->getSchool('colegio-aislamiento');
        $aisGuardian = $this->getGuardianOf($aislamiento);
        $liceoStudent = $this->getStudentOf($liceo);

        $this->client->loginUser($aisGuardian, 'main');
        $this->client->request('GET', '/guardian/dashboard');

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $content = $this->client->getResponse()->getContent();
        $this->assertStringNotContainsString(
            (string) $liceoStudent->getId(),
            $content ?: '',
            'Guardian dashboard must not expose student IDs from another school.'
        );
    }

    // ── EasyAdmin batch-delete isolation ─────────────────────────────────────

    /**
     * AdminAislamiento attempts batch-delete on a Liceo Ficticio course ID.
     * The voter must block it (403) or silently skip the foreign entity.
     */
    public function testAdminCannotBatchDeleteCourseFromOtherSchool(): void
    {
        $liceo       = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento = $this->getSchool('colegio-aislamiento');
        $aisAdmin    = $this->getAdminOf($aislamiento);
        $liceoCourse = $this->getCourseBelongingTo($liceo);

        $this->client->loginUser($aisAdmin, 'main');
        $this->client->request('POST', '/admin/course/batch-delete', [
            'batchActionEntityIds' => [$liceoCourse->getId()],
        ]);

        // The course from Liceo must still exist after the attempt.
        $this->em->clear();
        $stillExists = $this->em->getRepository(\App\Entity\Course::class)->find($liceoCourse->getId());
        $this->assertNotNull($stillExists, 'Cross-school batch-delete must not remove the entity.');
    }

    // ── EasyAdmin autocomplete isolation ─────────────────────────────────────

    /**
     * The admin/course autocomplete endpoint must not return courses
     * belonging to a different school.
     */
    public function testAdminAutocompleteDoesNotLeakCoursesFromOtherSchool(): void
    {
        $liceo       = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento = $this->getSchool('colegio-aislamiento');
        $aisAdmin    = $this->getAdminOf($aislamiento);
        $liceoCourse = $this->getCourseBelongingTo($liceo);

        $this->client->loginUser($aisAdmin, 'main');
        $this->client->request('GET', '/admin/course/autocomplete', ['query' => $liceoCourse->getName()]);

        $status = $this->client->getResponse()->getStatusCode();
        if ($status === 200) {
            $data = json_decode($this->client->getResponse()->getContent() ?: '{}', true);
            $ids  = array_column($data['results'] ?? [], 'value');
            $this->assertNotContains(
                (string) $liceoCourse->getId(),
                $ids,
                'Autocomplete must not return courses from another school.'
            );
        }
        // A 403/404/302 also satisfies the requirement.
    }

    // ── Helpers (extra) ───────────────────────────────────────────────────────

    /** Return a second ROLE_TEACHER of $school, different from $exclude. */
    private function getSecondTeacherOf(School $school, User $exclude): ?User
    {
        $users = $this->em->getRepository(User::class)->findBy(['school' => $school]);
        foreach ($users as $user) {
            if ($user->getId() !== $exclude->getId() && in_array('ROLE_TEACHER', $user->getRoles(), true)) {
                return $user;
            }
        }
        return null;
    }
}
