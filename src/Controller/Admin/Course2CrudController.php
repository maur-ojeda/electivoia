<?php

namespace App\Controller\Admin;

use App\Entity\Course;
use App\Service\TenantContext;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;

/**
 * Secondary course CRUD scaffold (to be merged into CourseCrudController in HU-U15).
 * Until then, apply the same school-isolation rules as the primary controller.
 */
class Course2CrudController extends AbstractCrudController
{
    public function __construct(private TenantContext $tenantContext) {}

    public static function getEntityFqcn(): string
    {
        return Course::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityPermission('SAME_SCHOOL');
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        if ($this->tenantContext->hasSchool() && !$this->isGranted('ROLE_SUPER_ADMIN')) {
            $qb->andWhere('entity.school = :_tenant_school')
                ->setParameter('_tenant_school', $this->tenantContext->getCurrentSchool());
        }

        return $qb;
    }
}
