<?php

namespace App\Repository;

use App\Entity\InterestProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InterestProfile>
 */
class InterestProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InterestProfile::class);
    }

    /**
     * Returns interest profiles from students in the same school, excluding the given user.
     *
     * @return InterestProfile[]
     */
    public function findOtherProfilesInSchool(int $schoolId, int $excludeUserId): array
    {
        return $this->createQueryBuilder('ip')
            ->innerJoin('ip.student', 's')
            ->where('s.school = :schoolId')
            ->andWhere('s.id != :excludeUserId')
            ->setParameter('schoolId', $schoolId)
            ->setParameter('excludeUserId', $excludeUserId)
            ->getQuery()
            ->getResult();
    }

    /**
     * Counts students in a school whose interest in the given category
     * is at or above the specified level.
     *
     * Uses PostgreSQL jsonb operator (->>) to read the interest level.
     * The interests column stores data as {"Category Name": level, ...}.
     */
    public function countStudentsInterestedInCategory(int $schoolId, string $categoryName, int $minLevel = 3): int
    {
        return (int) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT COUNT(DISTINCT ip.id)'
            . ' FROM interest_profile ip'
            . ' INNER JOIN "user" s ON s.id = ip.student_id'
            . ' WHERE s.school_id = :schoolId'
            . ' AND (ip.interests::jsonb ->> :key)::float >= :minLevel',
            ['schoolId' => $schoolId, 'key' => $categoryName, 'minLevel' => $minLevel]
        );
    }

    /**
     * Counts total interest profiles for students in the given school.
     */
    public function countProfilesInSchool(int $schoolId): int
    {
        return (int) $this->createQueryBuilder('ip')
            ->select('COUNT(DISTINCT ip.id)')
            ->innerJoin('ip.student', 's')
            ->where('s.school = :schoolId')
            ->setParameter('schoolId', $schoolId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    //    /**
    //     * @return InterestProfile[] Returns an array of InterestProfile objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('i')
    //            ->andWhere('i.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('i.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?InterestProfile
    //    {
    //        return $this->createQueryBuilder('i')
    //            ->andWhere('i.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
