<?php

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),
            EmailField::new('email'),


            ChoiceField::new('roles')
                ->setChoices([
                    'Administrador' => 'ROLE_ADMIN',
                    'Profesor' => 'ROLE_TEACHER',
                    'Estudiante' => 'ROLE_STUDENT',
                    'Apoderado' => 'ROLE_GUARDIAN',
                ])
                ->allowMultipleChoices(true)
                ->renderExpanded(false)
                ->renderAsBadges(),


        ];
    }
}
