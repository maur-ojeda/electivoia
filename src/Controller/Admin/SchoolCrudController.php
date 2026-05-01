<?php

namespace App\Controller\Admin;

use App\Entity\School;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
class SchoolCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return School::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Colegio')
            ->setEntityLabelInPlural('Colegios')
            ->setDefaultSort(['name' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('name', 'Nombre');
        yield TextField::new('slug', 'Slug')->setHelp('Identificador único. Solo minúsculas, sin espacios.');
        yield TextField::new('rbd', 'RBD')->setRequired(false)->setHelp('Rol Base de Datos (código chileno del colegio).');
        yield ChoiceField::new('plan', 'Plan')
            ->setChoices(['Free' => 'free', 'Basic' => 'basic', 'Premium' => 'premium']);
        yield BooleanField::new('active', 'Activo');
        yield DateTimeField::new('enrollmentStart', 'Inicio inscripciones')
            ->setRequired(false)
            ->setHelp('Deja vacío para sin restricción de inicio.');
        yield DateTimeField::new('enrollmentEnd', 'Cierre inscripciones')
            ->setRequired(false)
            ->setHelp('Deja vacío para sin restricción de cierre.');
        yield DateTimeField::new('createdAt', 'Creado el')->onlyOnDetail()->onlyOnIndex();
    }
}
