<?php

namespace App\Repository;

use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\School;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
     */
    public function findEnrollmentsByCourseOrderedByGradeAsc(Course $course): array
    {
        $qb = $this->createQueryBuilder('e')
            ->join('e.student', 's')
            ->where('e.course = :course')
            ->setParameter('course', $course)
            // COALESCE is not part of DQL; CASE yields the same "null counts as 0.0"
            // ordering and is portable across the supported database engines.
            ->orderBy('CASE WHEN s.averageGrade IS NULL THEN 0.0 ELSE s.averageGrade END', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Returns all enrollments for the given students.
     *
     * @param User[] $students
     *
     * @return Enrollment[]
     */
    public function findEnrollmentsByStudents(array $students): array
    {
        if (empty($students)) {
            return [];
        }

        $studentIds = array_map(fn (User $s) => $s->getId(), $students);
        $studentIds = array_filter($studentIds, fn ($id) => null !== $id);

        if (empty($studentIds)) {
            return [];
        }

        return $this->createQueryBuilder('e')
            ->where('e.student IN (:studentIds)')
            ->setParameter('studentIds', $studentIds)
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns enrollment demographics (category name, student gender, student grade)
     * for all active courses in the given school.
     *
     * @return array<int, array{category: string, gender: ?string, grade: ?string}>
     */
    public function findEnrollmentDemographicsForSchool(School $school): array
    {
        return $this->createQueryBuilder('e')
            ->select('cat.name as category')
            ->addSelect('s.gender as gender')
            ->addSelect('s.grade as grade')
            ->join('e.course', 'c')
            ->join('c.category', 'cat')
            ->join('e.student', 's')
            ->where('c.school = :school')
            ->andWhere('c.isActive = true')
            ->setParameter('school', $school)
            ->getQuery()
            ->getArrayResult();
    }
}
