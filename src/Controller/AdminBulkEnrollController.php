<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\User;
use App\Service\TenantContext;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/enrollments', name: 'admin_enrollments_')]
#[IsGranted('ROLE_ADMIN')]
class AdminBulkEnrollController extends AbstractController
{
    public function __construct(private TenantContext $tenantContext) {}

    #[Route('/bulk', name: 'bulk', methods: ['GET', 'POST'])]
    public function bulk(Request $request, EntityManagerInterface $em): Response
    {
        $courseQb = $em->getRepository(Course::class)->createQueryBuilder('c')
            ->where('c.isActive = true')
            ->orderBy('c.name', 'ASC');

        if ($this->tenantContext->hasSchool()) {
            $courseQb->andWhere('c.school = :_school')
                ->setParameter('_school', $this->tenantContext->getCurrentSchool());
        }

        $courses = $courseQb->getQuery()->getResult();
        $grades  = ['3M', '4M'];
        $result  = null;

        if ($request->isMethod('POST')) {
            $courseId = (int) $request->request->get('course_id');
            $grade    = $request->request->get('grade');

            $course = $em->getRepository(Course::class)->find($courseId);

            if (!$course) {
                $this->addFlash('error', 'Curso no encontrado.');
                return $this->redirectToRoute('admin_enrollments_bulk');
            }

            if (!in_array($grade, $grades)) {
                $this->addFlash('error', 'Grado inválido.');
                return $this->redirectToRoute('admin_enrollments_bulk');
            }

            // Check deadline
            if ($course->getEnrollmentDeadline() && $course->getEnrollmentDeadline() < new \DateTimeImmutable()) {
                $this->addFlash('error', 'El período de inscripción para este curso ha cerrado.');
                return $this->redirectToRoute('admin_enrollments_bulk');
            }

            // Students of the selected grade
            // NOTE: u.roles is a JSON column in PostgreSQL — LIKE on raw JSON is not supported.
            // We cast to TEXT so the LIKE operator works correctly.
            // Doctrine DQL does not support CAST(), so we use a native SQL query with
            // ResultSetMappingBuilder to hydrate proper User entities.
            $rsm = new ResultSetMappingBuilder($em);
            $rsm->addRootEntityFromClassMetadata(User::class, 'u');

            $sql = 'SELECT u.* FROM "user" u'
                . ' WHERE u.grade = :grade'
                . ' AND u.active = true'
                . ' AND CAST(u.roles AS TEXT) LIKE :role';

            $params = ['grade' => $grade, 'role' => '%ROLE_STUDENT%'];

            if ($this->tenantContext->hasSchool()) {
                $sql .= ' AND u.school_id = :schoolId';
                $params['schoolId'] = $this->tenantContext->getCurrentSchool()->getId();
            }

            $students = $em->createNativeQuery($sql, $rsm)->setParameters($params)->getResult();

            $enrolled  = 0;
            $skipped   = 0; // already enrolled
            $rejected  = 0; // over capacity

            try {
                $em->beginTransaction();

                // Pessimistic lock on the course row to prevent race conditions
                // on capacity checks during concurrent enrollments.
                $em->createQuery('SELECT c FROM App\Entity\Course c WHERE c.id = :id')
                    ->setParameter('id', $course->getId())
                    ->setLockMode(LockMode::PESSIMISTIC_WRITE)
                    ->getSingleResult();

                foreach ($students as $student) {
                    // Already enrolled?
                    $existing = $em->getRepository(Enrollment::class)->findOneBy([
                        'student' => $student,
                        'course'  => $course,
                    ]);
                    if ($existing) {
                        $skipped++;
                        continue;
                    }

                    // Capacity check (now safe under pessimistic lock)
                    $currentCount = $em->getRepository(Enrollment::class)->count(['course' => $course]);
                    if ($currentCount >= $course->getMaxCapacity()) {
                        $rejected++;
                        continue;
                    }

                    $enrollment = new Enrollment();
                    $enrollment->setStudent($student);
                    $enrollment->setCourse($course);
                    $enrollment->setEnrolledAt(new \DateTime());
                    $em->persist($enrollment);
                    $enrolled++;
                }

                $em->flush();
                $em->commit();
            } catch (\Doctrine\ORM\PessimisticLockException $e) {
                $em->rollback();
                $this->addFlash('error', 'Otro proceso está inscribiendo en este curso. Inténtalo de nuevo.');
                return $this->redirectToRoute('admin_enrollments_bulk');
            } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
                $em->rollback();
                $this->addFlash('error', 'Se detectó una inscripción duplicada. Verifica los datos e inténtalo de nuevo.');
                return $this->redirectToRoute('admin_enrollments_bulk');
            }

            $result = [
                'course'   => $course,
                'grade'    => $grade,
                'enrolled' => $enrolled,
                'skipped'  => $skipped,
                'rejected' => $rejected,
            ];
        }

        return $this->render('admin/bulk_enroll.html.twig', [
            'courses' => $courses,
            'grades'  => $grades,
            'result'  => $result,
        ]);
    }
}
