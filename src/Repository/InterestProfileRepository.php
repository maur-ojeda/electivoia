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
