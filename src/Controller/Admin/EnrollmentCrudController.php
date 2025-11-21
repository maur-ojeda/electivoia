<?php

namespace App\Controller\Admin;

use App\Entity\Course;
use App\Entity\Enrollment;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField; // Importante para relaciones
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;

class EnrollmentCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Enrollment::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Inscripción')
            ->setEntityLabelInPlural('Inscripciones')
            ->setPageTitle('index', 'Listado de Alumnos Inscritos')
            ->setSearchFields(['student.fullName', 'course.name']);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            // Para la página de listado (index), mostramos el nombre completo como texto.
            TextField::new('student.fullName', 'Alumno')->onlyOnIndex(),
            TextField::new('student.grade', 'Curso')->onlyOnIndex(),
            // Para los formularios (new/edit), usamos el campo de asociación para poder seleccionar un alumno.
            AssociationField::new('student', 'Alumno')->onlyOnForms(),

            AssociationField::new('course', 'Curso Inscrito'),
            DateTimeField::new('enrolledAt', 'Fecha de Inscripción')
                ->setFormat('dd/MM/yyyy HH:mm')
                ->onlyOnIndex(),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action->setLabel('Dar de baja')->setIcon('fa fa-user-times');
            })
            ->update(Crud::PAGE_DETAIL, Action::DELETE, function (Action $action) {
                return $action->setLabel('Dar de baja')->setIcon('fa fa-user-times');
            });
    }
}
