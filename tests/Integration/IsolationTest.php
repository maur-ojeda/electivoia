<?php

namespace App\Tests\Integration;

use App\Entity\Course;
use App\Entity\CourseCategory;
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
    /** @var object[] Entities created by a test, deleted in tearDown. */
    private array $entitiesToDelete = [];

    protected function setUp(): void
    {
        parent::setUp();
        static::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');
        $this->entitiesToDelete = [];
        // Purge any test users left by a previously interrupted run.
        $this->em->createQuery('DELETE FROM App\Entity\User u WHERE u.email LIKE :pattern')
            ->setParameter('pattern', '%-iso@test.local')
            ->execute();
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->entitiesToDelete) as $entity) {
            $refreshed = $this->em->find($entity::class, $entity->getId());
            if ($refreshed !== null) {
                $this->em->remove($refreshed);
            }
        }
        if ($this->entitiesToDelete !== []) {
            $this->em->flush();
        }
        $this->entitiesToDelete = [];
        parent::tearDown();
    }

    /**
     * Creates a transient test user and schedules it for deletion in tearDown.
     */
    private function createTestUser(
        string $email,
        array  $roles,
        School $school,
        string $grade = '',
    ): User {
        $user = new User();
        $user->setEmail($email);
        $user->setRoles($roles);
        $user->setSchool($school);
        $user->setActive(true);
        $user->setFullName('Test ' . substr(md5($email), 0, 12));
        // Fake RUT: use a range (99.000.XXX-X) unlikely to collide with seed data.
        $n = abs(crc32($email)) % 900 + 100;
        $user->setRut("99.000.{$n}-" . ($n % 9 + 1));

        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get('security.user_password_hasher');
        $user->setPassword($hasher->hashPassword($user, 'Test1234!'));

        if ($grade !== '') {
            $user->setGrade($grade);
        }

        $this->em->persist($user);
        $this->em->flush();
        $this->entitiesToDelete[] = $user;
        return $user;
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
        $this->client->request('GET', '/admin/course/' . $liceoCourse->getId());

        $this->assertContains(
            $this->client->getResponse()->getStatusCode(),
            [403, 404],
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
        $this->assertContains($this->client->getResponse()->getStatusCode(), [403, 404]);
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
        $this->assertContains($this->client->getResponse()->getStatusCode(), [403, 404]);
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
        $this->client->request('GET', '/admin/user/' . $liceoUser->getId());

        $this->assertContains($this->client->getResponse()->getStatusCode(), [403, 404]);
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
        $this->assertContains($this->client->getResponse()->getStatusCode(), [403, 404]);
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
            [403, 404],
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

        $this->assertContains($this->client->getResponse()->getStatusCode(), [403, 404]);
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
            [403, 404],
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

        $this->assertContains($this->client->getResponse()->getStatusCode(), [403, 404]);
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
            [403, 404],
            'Teacher must not access attendance of a colleague\'s course.'
        );
    }

    // ── Guardian isolation ────────────────────────────────────────────────────

    /**
     * A guardian from Colegio Aislamiento sees only their own linked pupils:
     *  - their own pupil appears
     *  - a student from Liceo Ficticio does NOT appear
     *  - a student from the SAME school who is NOT their pupil does NOT appear
     */
    public function testGuardianDashboardDoesNotLeakStudentsFromOtherSchool(): void
    {
        $liceo       = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento = $this->getSchool('colegio-aislamiento');

        // This test creates its own data so it never depends on seed guardians.
        $guardian        = $this->createTestUser('test-guardian-iso@test.local',    ['ROLE_GUARDIAN'], $aislamiento);
        $pupil           = $this->createTestUser('test-pupil-iso@test.local',       ['ROLE_STUDENT'],  $aislamiento);
        $foreignStudent  = $this->createTestUser('test-foreign-iso@test.local',     ['ROLE_STUDENT'],  $liceo);
        $sameSchoolNonPupil = $this->createTestUser('test-nopupil-iso@test.local', ['ROLE_STUDENT'], $aislamiento);

        $guardian->addGuardianStudent($pupil);
        // sameSchoolNonPupil is NOT linked to this guardian
        $this->em->flush();

        $this->client->loginUser($guardian, 'main');
        $this->client->request('GET', '/guardian/dashboard');

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $content = $this->client->getResponse()->getContent() ?: '';

        // Template renders fullName, not ID.
        $this->assertStringContainsString(
            (string) $pupil->getFullName(),
            $content,
            'Guardian dashboard must show their own pupil.'
        );
        $this->assertStringNotContainsString(
            (string) $foreignStudent->getFullName(),
            $content,
            'Guardian dashboard must not expose students from another school.'
        );
        $this->assertStringNotContainsString(
            (string) $sameSchoolNonPupil->getFullName(),
            $content,
            'Guardian dashboard must not expose same-school students who are not their pupils.'
        );
    }

    // ── EasyAdmin batch-delete isolation ─────────────────────────────────────

    /**
     * AdminAislamiento attempts batch-delete on a Liceo Ficticio course ID.
     *
     * The token must be valid: EasyAdmin validates CSRF before permissions,
     * so an untokened request is rejected by CSRF and never reaches the voter.
     */
    public function testAdminCannotBatchDeleteCourseFromOtherSchool(): void
    {
        $liceo       = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento = $this->getSchool('colegio-aislamiento');
        $aisAdmin    = $this->getAdminOf($aislamiento);
        $liceoCourse = $this->getCourseBelongingTo($liceo);

        $this->client->loginUser($aisAdmin, 'main');
        $csrfToken = $this->batchDeleteToken('/admin/course');
        $this->client->request('POST', '/admin/course/batch-delete', [
            'batchActionName' => 'batchDelete',
            'batchActionEntityIds' => [$liceoCourse->getId()],
            'batchActionCsrfToken' => $csrfToken,
            'entityFqcn' => Course::class,
        ]);

        $this->assertContains(
            $this->client->getResponse()->getStatusCode(),
            [403, 404],
            'Cross-school batch-delete must be refused by the voter, not by CSRF.'
        );

        // The course from Liceo must still exist after the attempt.
        $this->em->clear();
        $stillExists = $this->em->getRepository(\App\Entity\Course::class)->find($liceoCourse->getId());
        $this->assertNotNull($stillExists, 'Cross-school batch-delete must not remove the entity.');
    }

    /**
     * The batch action resolves its target repository from the posted
     * entityFqcn, so without a check a school admin could delete entities of
     * any class by posting a different FQCN to any batch endpoint.
     */
    public function testBatchDeleteRejectsForeignEntityClass(): void
    {
        $liceo       = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento = $this->getSchool('colegio-aislamiento');
        $aisAdmin    = $this->getAdminOf($aislamiento);
        $victim      = $this->createTestUser('test-batch-fqcn-iso@test.local', ['ROLE_STUDENT'], $liceo);
        $victimId    = $victim->getId();

        $this->client->loginUser($aisAdmin, 'main');

        // Course endpoint, User entity class.
        $this->client->request('POST', '/admin/course/batch-delete', [
            'batchActionName' => 'batchDelete',
            'batchActionEntityIds' => [$victimId],
            'batchActionCsrfToken' => $this->batchDeleteToken('/admin/course'),
            'entityFqcn' => User::class,
        ]);

        $this->assertContains(
            $this->client->getResponse()->getStatusCode(),
            [403, 404],
            'A batch action must not accept an entity class other than its own.'
        );

        $this->em->clear();
        $this->assertNotNull(
            $this->em->find(User::class, $victimId),
            'Batch-delete through a mismatched entity class must not remove the entity.'
        );
    }

    /**
     * Positive control for the batch guard: an admin can still batch-delete an
     * enrollment of their own school.
     */
    public function testAdminCanBatchDeleteOwnSchoolEnrollment(): void
    {
        $aislamiento = $this->getSchool('colegio-aislamiento');
        $aisAdmin    = $this->getAdminOf($aislamiento);
        $student     = $this->createTestUser('test-own-batch-iso@test.local', ['ROLE_STUDENT'], $aislamiento);
        $enrollment  = $this->createEnrollment($student, $this->getCourseBelongingTo($aislamiento));
        $id          = $enrollment->getId();

        $this->client->loginUser($aisAdmin, 'main');
        $csrfToken = $this->batchDeleteToken('/admin/enrollment');
        $this->client->request('POST', '/admin/enrollment/batch-delete', [
            'batchActionName' => 'batchDelete',
            'batchActionEntityIds' => [$id],
            'batchActionCsrfToken' => $csrfToken,
            'entityFqcn' => \App\Entity\Enrollment::class,
        ]);

        $this->assertSame(
            302,
            $this->client->getResponse()->getStatusCode(),
            'An own-school batch-delete must be allowed.'
        );

        $this->em->clear();
        $this->assertNull(
            $this->em->find(\App\Entity\Enrollment::class, $id),
            'An own-school batch-delete must remove the entity.'
        );
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
        $aisCourse   = $this->getCourseBelongingTo($aislamiento);

        $this->client->loginUser($aisAdmin, 'main');

        // Positive control: a course of the admin's own school IS offered.
        $this->assertContains(
            (string) $aisCourse->getId(),
            $this->autocompleteCourseIds((string) $aisCourse->getName()),
            'The course selector must offer courses from the admin\'s own school.'
        );

        // Negative: a course from another school is NOT offered.
        $this->assertNotContains(
            (string) $liceoCourse->getId(),
            $this->autocompleteCourseIds((string) $liceoCourse->getName()),
            'Autocomplete must not return courses from another school.'
        );
    }

    /** @return list<string> Ids returned by the enrollment form's course autocomplete. */
    private function autocompleteCourseIds(string $query): array
    {
        $this->client->request('GET', '/admin/course/autocomplete', [
            'query' => $query,
            'autocompleteContext' => [
                'propertyName' => 'course',
                'originatingPage' => 'edit',
                'crudControllerFqcn' => \App\Controller\Admin\EnrollmentCrudController::class,
            ],
        ]);

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());

        $data = json_decode($this->client->getResponse()->getContent() ?: '{}', true);

        return array_map('strval', array_column($data['results'] ?? [], 'entityId'));
    }

    // ── Email blast isolation ─────────────────────────────────────────────────

    /**
     * ROLE_ADMIN can access /admin/email/blast and the page loads without error.
     */
    public function testEmailBlastAccessibleByAdmin(): void
    {
        $aislamiento = $this->getSchool('colegio-aislamiento');
        $aisAdmin    = $this->getAdminOf($aislamiento);

        $this->client->loginUser($aisAdmin, 'main');
        $this->client->request('GET', '/admin/email/blast');

        $this->assertSame(200, $this->client->getResponse()->getStatusCode(),
            'ROLE_ADMIN must be able to GET /admin/email/blast.');
    }

    /**
     * The email blast page (GET) must not expose data belonging to another school.
     * We create a Liceo Ficticio student with a recognizable test email and verify
     * that email does not appear anywhere in the Aislamiento admin's blast page.
     */
    public function testEmailBlastFiltersScopeBySchool(): void
    {
        $liceo       = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento = $this->getSchool('colegio-aislamiento');

        $liceoStudent = $this->createTestUser(
            'test-blast-liceo-spy@test.local',
            ['ROLE_STUDENT'],
            $liceo,
            '3M'
        );

        $aisAdmin = $this->getAdminOf($aislamiento);
        $this->client->loginUser($aisAdmin, 'main');
        $this->client->request('GET', '/admin/email/blast');

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $content = $this->client->getResponse()->getContent() ?: '';

        // Template shows grade stats (counts), never individual emails or IDs.
        // Verify the Liceo student's email does not leak into the page.
        $this->assertStringNotContainsString(
            'test-blast-liceo-spy@test.local',
            $content,
            'Email blast page must not expose students from another school.'
        );
    }

    // ── Course category ROLE_ADMIN restrictions ───────────────────────────────

    /**
     * ROLE_ADMIN must receive 403 when trying to create, edit, or delete a
     * CourseCategory via direct URL — only ROLE_SUPER_ADMIN may manage categories.
     */
    public function testAdminCannotManageCategoryViaUrl(): void
    {
        $aislamiento = $this->getSchool('colegio-aislamiento');
        $aisAdmin    = $this->getAdminOf($aislamiento);

        $this->client->loginUser($aisAdmin, 'main');

        // 1. Create — no entity ID required.
        $this->client->request('GET', '/admin/course-category/new');
        $this->assertSame(
            403,
            $this->client->getResponse()->getStatusCode(),
            'ROLE_ADMIN must receive 403 on GET /admin/course-category/new.'
        );

        // 2. Edit / Delete — need an existing category ID.
        $category = $this->em->getRepository(CourseCategory::class)->findOneBy([]);
        if ($category === null) {
            // Create a minimal category just for this assertion.
            $category = new CourseCategory();
            $category->setName('__test_cat__');
            $this->em->persist($category);
            $this->em->flush();
            $this->entitiesToDelete[] = $category;
        }

        $this->client->request('GET', '/admin/course-category/' . $category->getId() . '/edit');
        $this->assertSame(
            403,
            $this->client->getResponse()->getStatusCode(),
            'ROLE_ADMIN must receive 403 on GET /admin/course-category/{id}/edit.'
        );

        $this->client->request('POST', '/admin/course-category/' . $category->getId() . '/delete');
        $this->assertContains(
            $this->client->getResponse()->getStatusCode(),
            [403, 404],
            'ROLE_ADMIN must receive 403/404 on POST /admin/course-category/{id}/delete.'
        );
    }

    // ── Cross-school ID for the remaining CRUDs (HU-S02 · T2) ─────────────────

    /** Create an enrollment for $student in $course and schedule cleanup. */
    private function createEnrollment(User $student, Course $course): \App\Entity\Enrollment
    {
        $enrollment = new \App\Entity\Enrollment();
        $enrollment->setStudent($student);
        $enrollment->setCourse($course);
        $this->em->persist($enrollment);
        $this->em->flush();
        $this->entitiesToDelete[] = $enrollment;

        return $enrollment;
    }

    /** Create an interest profile for $student and schedule cleanup. */
    private function createInterestProfile(User $student): \App\Entity\InterestProfile
    {
        $profile = new \App\Entity\InterestProfile();
        $profile->setStudent($student);
        $profile->setInterests(['Filosofía' => 4]);
        $this->em->persist($profile);
        $this->em->flush();
        $this->entitiesToDelete[] = $profile;

        return $profile;
    }

    /**
     * Fetches the batch-action CSRF token from the rendered index page, the
     * same way the browser does. EasyAdmin's ActionFactory emits it as
     * data-action-csrf-token.
     *
     * This matters: batchDelete() validates CSRF *before* the entity
     * permission check, so an untokened request is rejected by CSRF and never
     * reaches the voter — the test would pass for the wrong reason.
     */
    private function batchDeleteToken(string $indexUrl): string
    {
        $crawler = $this->client->request('GET', $indexUrl);
        $node    = $crawler->filter('[data-action-csrf-token]');

        $this->assertGreaterThan(
            0,
            $node->count(),
            sprintf('No batch-action CSRF token rendered on %s.', $indexUrl)
        );

        return (string) $node->first()->attr('data-action-csrf-token');
    }

    public function testAdminCannotViewEnrollmentFromOtherSchool(): void
    {
        $liceo       = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento = $this->getSchool('colegio-aislamiento');
        $aisAdmin    = $this->getAdminOf($aislamiento);
        $liceoCourse = $this->getCourseBelongingTo($liceo);
        $liceoStudent = $this->getStudentOf($liceo);
        $enrollment  = $this->createEnrollment($liceoStudent, $liceoCourse);

        $this->client->loginUser($aisAdmin, 'main');
        $this->client->request('GET', '/admin/enrollment/' . $enrollment->getId());

        $this->assertContains(
            $this->client->getResponse()->getStatusCode(),
            [403, 404],
            'An enrollment from another school must not be viewable.'
        );
    }

    public function testAdminCannotEditEnrollmentFromOtherSchool(): void
    {
        $liceo       = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento = $this->getSchool('colegio-aislamiento');
        $aisAdmin    = $this->getAdminOf($aislamiento);
        $liceoCourse = $this->getCourseBelongingTo($liceo);
        $liceoStudent = $this->getStudentOf($liceo);
        $enrollment  = $this->createEnrollment($liceoStudent, $liceoCourse);

        $this->client->loginUser($aisAdmin, 'main');
        $this->client->request('GET', '/admin/enrollment/' . $enrollment->getId() . '/edit');

        $this->assertContains($this->client->getResponse()->getStatusCode(), [403, 404]);
    }

    public function testAdminCannotDeleteEnrollmentFromOtherSchool(): void
    {
        $liceo       = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento = $this->getSchool('colegio-aislamiento');
        $aisAdmin    = $this->getAdminOf($aislamiento);
        $liceoCourse = $this->getCourseBelongingTo($liceo);
        $liceoStudent = $this->getStudentOf($liceo);
        $enrollment  = $this->createEnrollment($liceoStudent, $liceoCourse);
        $id = $enrollment->getId();

        $this->client->loginUser($aisAdmin, 'main');
        $this->client->request('POST', '/admin/enrollment/' . $id . '/delete');

        $this->assertContains($this->client->getResponse()->getStatusCode(), [403, 404]);
        $this->em->clear();
        $this->assertNotNull(
            $this->em->find(\App\Entity\Enrollment::class, $id),
            'Cross-school delete must not remove the enrollment.'
        );
    }

    public function testAdminCannotBatchDeleteEnrollmentFromOtherSchool(): void
    {
        $liceo       = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento = $this->getSchool('colegio-aislamiento');
        $aisAdmin    = $this->getAdminOf($aislamiento);
        $liceoCourse = $this->getCourseBelongingTo($liceo);
        $liceoStudent = $this->getStudentOf($liceo);
        $enrollment  = $this->createEnrollment($liceoStudent, $liceoCourse);
        $id = $enrollment->getId();

        $this->client->loginUser($aisAdmin, 'main');
        $csrfToken = $this->batchDeleteToken('/admin/enrollment');
        $this->client->request('POST', '/admin/enrollment/batch-delete', [
            'batchActionName' => 'batchDelete',
            'batchActionEntityIds' => [$id],
            'batchActionCsrfToken' => $csrfToken,
            'entityFqcn' => \App\Entity\Enrollment::class,
        ]);

        $this->assertContains(
            $this->client->getResponse()->getStatusCode(),
            [403, 404],
            'Cross-school batch-delete must be refused by the voter, not by CSRF.'
        );

        $this->em->clear();
        $this->assertNotNull($this->em->find(\App\Entity\Enrollment::class, $id));
    }

    public function testAdminCannotViewInterestProfileFromOtherSchool(): void
    {
        $liceo        = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento  = $this->getSchool('colegio-aislamiento');
        $aisAdmin     = $this->getAdminOf($aislamiento);
        $liceoStudent = $this->createTestUser('test-profile-owner-iso@test.local', ['ROLE_STUDENT'], $liceo);
        $profile      = $this->createInterestProfile($liceoStudent);

        $this->client->loginUser($aisAdmin, 'main');
        $this->client->request('GET', '/admin/interest-profile/' . $profile->getId());

        $this->assertContains($this->client->getResponse()->getStatusCode(), [403, 404]);
    }

    public function testAdminCannotEditInterestProfileFromOtherSchool(): void
    {
        $liceo        = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento  = $this->getSchool('colegio-aislamiento');
        $aisAdmin     = $this->getAdminOf($aislamiento);
        $liceoStudent = $this->createTestUser('test-profile-edit-iso@test.local', ['ROLE_STUDENT'], $liceo);
        $profile      = $this->createInterestProfile($liceoStudent);

        $this->client->loginUser($aisAdmin, 'main');
        $this->client->request('GET', '/admin/interest-profile/' . $profile->getId() . '/edit');

        $this->assertContains($this->client->getResponse()->getStatusCode(), [403, 404]);
    }

    public function testAdminCannotDeleteInterestProfileFromOtherSchool(): void
    {
        $liceo        = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento  = $this->getSchool('colegio-aislamiento');
        $aisAdmin     = $this->getAdminOf($aislamiento);
        $liceoStudent = $this->createTestUser('test-profile-del-iso@test.local', ['ROLE_STUDENT'], $liceo);
        $profile      = $this->createInterestProfile($liceoStudent);
        $id = $profile->getId();

        $this->client->loginUser($aisAdmin, 'main');
        $this->client->request('POST', '/admin/interest-profile/' . $id . '/delete');

        $this->assertContains($this->client->getResponse()->getStatusCode(), [403, 404]);
        $this->em->clear();
        $this->assertNotNull($this->em->find(\App\Entity\InterestProfile::class, $id));
    }

    public function testAdminCannotBatchDeleteUserFromOtherSchool(): void
    {
        $liceo       = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento = $this->getSchool('colegio-aislamiento');
        $aisAdmin    = $this->getAdminOf($aislamiento);
        $liceoStudent = $this->createTestUser('test-user-batch-iso@test.local', ['ROLE_STUDENT'], $liceo);
        $id = $liceoStudent->getId();

        $this->client->loginUser($aisAdmin, 'main');
        $csrfToken = $this->batchDeleteToken('/admin/user');
        $this->client->request('POST', '/admin/user/batch-delete', [
            'batchActionName' => 'batchDelete',
            'batchActionEntityIds' => [$id],
            'batchActionCsrfToken' => $csrfToken,
            'entityFqcn' => User::class,
        ]);

        $this->assertContains(
            $this->client->getResponse()->getStatusCode(),
            [403, 404],
            'Cross-school batch-delete must be refused by the voter, not by CSRF.'
        );

        $this->em->clear();
        $this->assertNotNull($this->em->find(User::class, $id));
    }

    /**
     * A teacher from another school cannot save attendance for a Liceo course,
     * and the existing attendance of that course must survive the attempt.
     */
    public function testTeacherCannotSaveAttendanceOfCourseFromOtherSchool(): void
    {
        $liceo        = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento  = $this->getSchool('colegio-aislamiento');
        $aisTeacher   = $this->getTeacherOf($aislamiento);
        $liceoStudent = $this->getStudentOf($liceo);
        $liceoCourse  = $this->getCourseBelongingTo($liceo);

        $today = new \DateTime();
        $existing = new \App\Entity\Attendance();
        $existing->setStudent($liceoStudent);
        $existing->setCourse($liceoCourse);
        $existing->setDate($today);
        $existing->setStatus('present');
        $this->em->persist($existing);
        $this->em->flush();
        $this->entitiesToDelete[] = $existing;
        $existingId = $existing->getId();

        $this->client->loginUser($aisTeacher, 'main');
        $this->client->request('POST', '/teacher/attendance/' . $liceoCourse->getId() . '/save', [
            'student_ids' => [$liceoStudent->getId()],
            'status' => [$liceoStudent->getId() => 'absent'],
        ]);

        $this->assertContains($this->client->getResponse()->getStatusCode(), [403, 404]);
        $this->em->clear();
        $survivor = $this->em->find(\App\Entity\Attendance::class, $existingId);
        $this->assertNotNull($survivor, 'Attendance of another school must not be deleted.');
        $this->assertSame('present', $survivor->getStatus(), 'Attendance of another school must not be overwritten.');
    }

    /**
     * S1/S3: a signal must not be recorded against a course from another school.
     */
    public function testStudentCannotRecordAiSignalForCourseFromOtherSchool(): void
    {
        $liceo       = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento = $this->getSchool('colegio-aislamiento');
        $aisStudent  = $this->createTestUser('test-signal-iso@test.local', ['ROLE_STUDENT'], $aislamiento);
        $liceoCourse = $this->getCourseBelongingTo($liceo);
        $studentId   = $aisStudent->getId();
        $courseId    = $liceoCourse->getId();

        $this->client->loginUser($aisStudent, 'main');
        $this->client->request(
            'POST',
            '/api/ai/signal',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['action' => 'view', 'course_id' => $courseId]),
        );

        $status = $this->client->getResponse()->getStatusCode();
        $signals = $this->em->getRepository(\App\Entity\AiSignal::class)
            ->findBy(['student' => $aisStudent, 'course' => $liceoCourse]);

        $created = count($signals);
        foreach ($signals as $signal) {
            $this->em->remove($signal);
        }
        $this->em->flush();

        $this->assertContains($status, [403, 404], 'Cross-school signal must be rejected.');
        $this->assertSame(0, $created, 'No signal may be stored against a course from another school.');
    }

    /**
     * Pins the valid path: the same endpoint still records signals for own-school courses.
     */
    public function testStudentCanRecordAiSignalForOwnSchoolCourse(): void
    {
        $aislamiento = $this->getSchool('colegio-aislamiento');
        $aisStudent  = $this->createTestUser('test-signal-own-iso@test.local', ['ROLE_STUDENT'], $aislamiento);
        $aisCourse   = $this->getCourseBelongingTo($aislamiento);

        $this->client->loginUser($aisStudent, 'main');
        $this->client->request(
            'POST',
            '/api/ai/signal',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['action' => 'view', 'course_id' => $aisCourse->getId()]),
        );

        $status = $this->client->getResponse()->getStatusCode();
        $signals = $this->em->getRepository(\App\Entity\AiSignal::class)
            ->findBy(['student' => $aisStudent, 'course' => $aisCourse]);
        $created = count($signals);
        foreach ($signals as $signal) {
            $this->em->remove($signal);
        }
        $this->em->flush();

        $this->assertSame(201, $status, 'Own-school signal must still be recorded.');
        $this->assertSame(1, $created);
    }

    /**
     * S2: the teacher selector in the course form must not offer teachers
     * belonging to another school.
     *
     * EasyAdmin resolves the autocomplete query builder from the target entity's
     * CRUD controller (UserCrudController), which filters by school. The
     * autocomplete context is required: without it the endpoint cannot resolve
     * the page name and returns 500.
     */
    public function testTeacherAutocompleteDoesNotLeakUsersFromOtherSchool(): void
    {
        $liceo        = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento  = $this->getSchool('colegio-aislamiento');
        $aisAdmin     = $this->getAdminOf($aislamiento);
        $liceoTeacher = $this->getTeacherOf($liceo);
        $aisTeacher   = $this->getTeacherOf($aislamiento);

        $this->client->loginUser($aisAdmin, 'main');

        // Positive control: a teacher of the admin's own school IS offered.
        // Without it the negative assertion below would also pass on an empty result.
        $ownIds = $this->autocompleteUserIds((string) $aisTeacher->getFullName());
        $this->assertContains(
            (string) $aisTeacher->getId(),
            $ownIds,
            'The user selector must offer teachers from the admin\'s own school.'
        );

        // Negative: a teacher of another school is NOT offered.
        $foreignIds = $this->autocompleteUserIds((string) $liceoTeacher->getFullName());
        $this->assertNotContains(
            (string) $liceoTeacher->getId(),
            $foreignIds,
            'The user selector must not offer teachers from another school.'
        );
    }

    /** @return list<string> Ids returned by the course form's teacher autocomplete. */
    private function autocompleteUserIds(string $query): array
    {
        $this->client->request('GET', '/admin/user/autocomplete', [
            'query' => $query,
            'autocompleteContext' => [
                'propertyName' => 'teacher',
                'originatingPage' => 'edit',
                'crudControllerFqcn' => \App\Controller\Admin\CourseCrudController::class,
            ],
        ]);

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());

        $data = json_decode($this->client->getResponse()->getContent() ?: '{}', true);

        return array_map('strval', array_column($data['results'] ?? [], 'entityId'));
    }

    /**
     * S2: the guardian pupils selector renders no student from another school.
     */
    public function testGuardianPupilsSelectorDoesNotOfferStudentsFromOtherSchool(): void
    {
        $liceo       = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento = $this->getSchool('colegio-aislamiento');
        $aisAdmin    = $this->getAdminOf($aislamiento);

        $guardian = $this->createTestUser('test-guardian-sel-iso@test.local', ['ROLE_GUARDIAN'], $aislamiento);
        $foreign  = $this->createTestUser('test-foreign-pupil-iso@test.local', ['ROLE_STUDENT'], $liceo);

        $this->client->loginUser($aisAdmin, 'main');
        $this->client->request('GET', '/admin/user/' . $guardian->getId() . '/edit');

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $content = $this->client->getResponse()->getContent() ?: '';
        $this->assertStringNotContainsString(
            (string) $foreign->getFullName(),
            $content,
            'The pupils selector must not offer students from another school.'
        );
    }

    /**
     * S5: the super admin keeps access to every school.
     */
    public function testSuperAdminCanViewCourseFromAnySchool(): void
    {
        $liceo       = $this->getSchool('liceo-ficticio-electivoia');
        $aislamiento = $this->getSchool('colegio-aislamiento');
        $liceoCourse = $this->getCourseBelongingTo($liceo);
        $superAdmin  = $this->createTestUser('test-superadmin-iso@test.local', ['ROLE_SUPER_ADMIN'], $aislamiento);

        $this->client->loginUser($superAdmin, 'main');
        $this->client->request('GET', '/admin/course/' . $liceoCourse->getId());

        $this->assertSame(200, $this->client->getResponse()->getStatusCode(),
            'The super admin must keep access to courses from any school.');
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
