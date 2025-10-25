<?php

// src/Controller/Admin/CourseCrudController.php
namespace App\Controller\Admin;

use App\Entity\Course;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;



class CourseCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Course::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('name', 'Nombre'),
            TextEditorField::new('description', 'Descripción')->onlyOnForms(),
            IntegerField::new('maxCapacity', 'Cupo máximo'),
            TextField::new('schedule', 'Horario'),
            DateTimeField::new('enrollmentDeadline', 'Fecha límite de inscripción')
                ->setHelp('Dejar vacío para permitir inscripciones sin límite de tiempo'),
            BooleanField::new('isActive', 'Activo'),
            AssociationField::new('teacher', 'Profesor')
                ->setCrudController(UserCrudController::class),
            TextareaField::new('targetGrades')
                ->setLabel('Grados objetivo (uno por línea)')
                ->formatValue(function ($value, Course $course) {
                    return implode("\n", $course->getTargetGrades());
                })
                ->setFormTypeOptions([
                    'attr' => ['rows' => 4],
                ])
                ->onlyOnForms(),
            // Mostrar como lista en la vista de índice
            TextareaField::new('targetGrades')
                ->setLabel('Grados')
                ->formatValue(function ($value, Course $course) {
                    return implode(', ', $course->getTargetGrades());
                })
                ->onlyOnIndex(),
        ];
    }
}
