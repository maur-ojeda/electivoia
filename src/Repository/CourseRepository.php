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

    /**
     * Returns active courses with available capacity, filtered by student grade and optional category/search.
     *
     * @param string|null $grade   Student grade to filter targetGrades (e.g. '3B')
     * @param string|null $category Category name to filter by
     * @param string|null $search  Search term for name or description
     * @return Course[]
     */
    public function findAvailableForStudent(
        ?string $grade = null,
        ?string $category = null,
        ?string $search = null,
    ): array {
        $qb = $this->createQueryBuilder('c')
            ->where('c.isActive = true')
            ->andWhere('c.currentEnrollment < c.maxCapacity');

        // Filter by target grade if provided
        if ($grade !== null) {
            $qb->andWhere('JSON_CONTAINS(c.targetGrades, :gradeJson) = 1')
                ->setParameter('gradeJson', json_encode($grade));
        }

        // Filter by category name if provided
        if ($category !== null) {
            $qb->innerJoin('c.category', 'cat')
                ->andWhere('cat.name = :category')
                ->setParameter('category', $category);
        }

        // Filter by search term on name or description
        if ($search !== null && $search !== '') {
            $qb->andWhere('(c.name LIKE :search OR c.description LIKE :search)')
                ->setParameter('search', '%' . $search . '%');
        }

        $qb->orderBy('c.name', 'ASC');

        return $this->withTenant($qb, 'c')->getQuery()->getResult();
    }
}
