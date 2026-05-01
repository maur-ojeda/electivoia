<?php

namespace App\Repository;

use App\Entity\Course;
use App\Service\TenantContext;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends TenantAwareRepository<Course>
 */
class CourseRepository extends TenantAwareRepository
{
    public function __construct(ManagerRegistry $registry, TenantContext $tenantContext)
    {
        parent::__construct($registry, Course::class, $tenantContext);
    }

    /** Returns active courses filtered by the current tenant (if any). */
    public function findActiveForContext(): array
    {
        $qb = $this->createQueryBuilder('c')->where('c.isActive = true');
        return $this->withTenant($qb, 'c')->getQuery()->getResult();
    }
}
