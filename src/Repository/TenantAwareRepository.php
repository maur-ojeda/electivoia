<?php

namespace App\Repository;

use App\Service\TenantContext;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Base repository that provides tenant-aware query helpers.
 *
 * Sprint 5: Infrastructure only — existing repositories don't extend this yet.
 * Sprint 6: Migrate each repository to extend this class and call withTenant().
 *
 * Usage in child repositories:
 *   $qb = $this->createQueryBuilder('c');
 *   $this->withTenant($qb, 'c');
 */
abstract class TenantAwareRepository extends ServiceEntityRepository
{
    protected TenantContext $tenantContext;

    public function __construct(ManagerRegistry $registry, string $entityClass, TenantContext $tenantContext)
    {
        parent::__construct($registry, $entityClass);
        $this->tenantContext = $tenantContext;
    }

    /**
     * Adds a school filter to the given QueryBuilder if a tenant is active.
     * The entity alias must have a `school` association (ManyToOne → School).
     */
    protected function withTenant(QueryBuilder $qb, string $alias): QueryBuilder
    {
        if (!$this->tenantContext->hasSchool()) {
            return $qb;
        }

        return $qb
            ->andWhere("{$alias}.school = :_tenant_school")
            ->setParameter('_tenant_school', $this->tenantContext->getCurrentSchool());
    }
}
