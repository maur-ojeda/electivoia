<?php

namespace App\Repository;

use App\Entity\School;
use App\Entity\User;
use App\Service\TenantContext;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends TenantAwareRepository<User>
 */
class UserRepository extends TenantAwareRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry, TenantContext $tenantContext)
    {
        parent::__construct($registry, User::class, $tenantContext);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Returns gender counts (M/F) for users in a school.
     *
     * @return array{M: int, F: int}
     */
    public function countByGenderForSchool(School $school): array
    {
        $results = $this->createQueryBuilder('u')
            ->select('u.gender')
            ->addSelect('COUNT(u.id) as cnt')
            ->where('u.school = :school')
            ->andWhere('u.gender IN (:genders)')
            ->setParameter('school', $school)
            ->setParameter('genders', ['M', 'F'])
            ->groupBy('u.gender')
            ->getQuery()
            ->getArrayResult();

        $counts = ['M' => 0, 'F' => 0];

        foreach ($results as $row) {
            $gender = $row['gender'];
            if (isset($counts[$gender])) {
                $counts[$gender] = (int) $row['cnt'];
            }
        }

        return $counts;
    }
}
