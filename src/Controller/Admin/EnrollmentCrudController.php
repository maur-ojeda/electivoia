<?php

namespace App\Controller\Admin;

use App\Entity\Enrollment;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField; // Importante para relaciones
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;


class EnrollmentCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Enrollment::class;
    }



    public function configureFields(string $pageName): iterable
    {
        return [
            // Campo ID (solo lectura en edición/visualización)
            IdField::new('id')->onlyOnIndex(), // Mostrar solo en la lista

            // Campo Fecha de Inscripción
            DateTimeField::new('enrolledAt', 'Fecha de Inscripción')
                ->setFormat('dd/MM/yyyy HH:mm'), // Formato legible

            // Campo Alumno (relación ManyToOne con User)
            AssociationField::new('student', 'Alumno')
                ->setCrudController(UserCrudController::class) // Si tienes un CRUD para User
                ->setRequired(true), // Obligatorio si es necesario

            // Campo Curso (relación ManyToOne con Course)
            AssociationField::new('course', 'Curso')
                ->setCrudController(CourseCrudController::class) // Si tienes un CRUD para Course
                ->setRequired(true), // Obligatorio si es necesario
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions

            ->disable(Action::EDIT);
    }
}
