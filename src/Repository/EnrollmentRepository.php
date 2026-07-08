<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends EntityRepository<Enrollment>
 */
class EnrollmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Enrollment::class);
    }

    // ... otros métodos ...

    /**
     * Finds enrollments for a specific course ordered by the student's average grade in ascending order.
     * Students without an average grade are considered to have the lowest priority (treated as 0.0).
     *
     * @param Course $course
     * @return array
     */
    public function findEnrollmentsByCourseOrderedByGradeAsc(Course $course): array
    {
        $qb = $this->createQueryBuilder('e')
            ->join('e.student', 's') // Asegúrate de que 'student' es el nombre correcto de la relación en Enrollment
            ->where('e.course = :course')
            ->setParameter('course', $course)
            ->orderBy('COALESCE(s.averageGrade, 0.0)', 'ASC'); // Ordena por promedio, tratando NULL como 0.0

        return $qb->getQuery()->getResult();
    }

    /**
     * Returns all enrollments for the given students.
     *
     * @param User[] $students
     * @return Enrollment[]
     */
    public function findEnrollmentsByStudents(array $students): array
    {
        if (empty($students)) {
            return [];
        }

        $studentIds = array_map(fn (User $s) => $s->getId(), $students);
        $studentIds = array_filter($studentIds, fn ($id) => $id !== null);

        if (empty($studentIds)) {
            return [];
        }

        return $this->createQueryBuilder('e')
            ->where('e.student IN (:studentIds)')
            ->setParameter('studentIds', $studentIds)
            ->getQuery()
            ->getResult();
    }
}
