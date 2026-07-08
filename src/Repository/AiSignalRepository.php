<?php

namespace App\Repository;

use App\Entity\AiSignal;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AiSignal>
 */
class AiSignalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiSignal::class);
    }

    /**
     * Returns recent signals for a student, newest first.
     *
     * @return AiSignal[]
     */
    public function findRecentForStudent(User $student, int $limit = 50): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.student = :student')
            ->setParameter('student', $student)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Counts signals of a specific action type for a student.
     */
    public function countByActionForStudent(User $student, string $action): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.student = :student')
            ->andWhere('s.action = :action')
            ->setParameter('student', $student)
            ->setParameter('action', $action)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Returns an array of course IDs the student has viewed.
     *
     * @return int[]
     */
    public function findViewedCourseIds(User $student): array
    {
        $result = $this->createQueryBuilder('s')
            ->select('DISTINCT s.course')
            ->andWhere('s.student = :student')
            ->andWhere('s.action = :action')
            ->andWhere('s.course IS NOT NULL')
            ->setParameter('student', $student)
            ->setParameter('action', AiSignal::ACTION_VIEW)
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'course');
    }

    /**
     * Counts distinct courses the student has viewed within a given category.
     */
    public function countViewedInCategory(User $student, int $categoryId): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(DISTINCT s.course)')
            ->innerJoin('s.course', 'c')
            ->innerJoin('c.category', 'cat')
            ->andWhere('s.student = :student')
            ->andWhere('s.action = :action')
            ->andWhere('cat.id = :categoryId')
            ->setParameter('student', $student)
            ->setParameter('action', AiSignal::ACTION_VIEW)
            ->setParameter('categoryId', $categoryId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
