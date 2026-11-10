<?php

namespace App\Controller\Admin;

use App\Entity\InterestProfile;
use App\Service\TenantContext;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class InterestProfileCrudController extends AbstractCrudController
{
    public function __construct(private TenantContext $tenantContext) {}

    public static function getEntityFqcn(): string
    {
        return InterestProfile::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Perfil IA')
            ->setEntityLabelInPlural('Perfiles IA')
            ->setEntityPermission('SAME_SCHOOL');
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        if ($this->tenantContext->hasSchool() && !$this->isGranted('ROLE_SUPER_ADMIN')) {
            $qb->join('entity.student', '_ip_u')
                ->andWhere('_ip_u.school = :_tenant_school')
                ->setParameter('_tenant_school', $this->tenantContext->getCurrentSchool());
        }

        return $qb;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),
            TextField::new('student.fullName', 'Alumno')
                ->onlyOnIndex(),
            TextField::new('student.rut', 'RUT')
                ->onlyOnIndex(),
            TextField::new('interestsBadge', 'Intereses')
                ->formatValue(function ($value) {
                    if ($value === 'sin-intereses') {
                        return '<span class="badge bg-secondary">Sin intereses</span>';
                    }
                    $items = explode(', ', $value);
                    $badges = [];
                    foreach ($items as $item) {
                        if (strpos($item, ':') === false) continue;
                        [$category, $score] = explode(':', $item, 2);
                        $color = match(true) {
                            $score >= 4 => 'success',
                            $score >= 2 => 'warning',
                            default => 'secondary',
                        };
                        $badges[] = sprintf('<span class="badge bg-%s me-1">%s: %s</span>', $color, htmlspecialchars($category), $score);
                    }
                    return implode(' ', $badges);
                })
                ->renderAsHtml(),
        ];
    }
}
