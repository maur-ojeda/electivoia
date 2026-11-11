<?php

namespace App\Controller\Admin;

use App\Entity\CourseCategory;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Course categories are global (shared across all schools).
 * Only ROLE_SUPER_ADMIN may create, edit or delete; ROLE_ADMIN is read-only.
 */
class CourseCategoryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return CourseCategory::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Categoría')
            ->setEntityLabelInPlural('Categorías');
    }

    public function configureActions(Actions $actions): Actions
    {
        if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
            return $actions
                ->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE);
        }

        return $actions;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('name', 'Nombre'),
        ];
    }
}
