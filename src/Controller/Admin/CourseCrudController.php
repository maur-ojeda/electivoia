<?php

namespace App\Controller\Admin;

use App\Entity\Enrollment;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use App\Entity\Course;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RequestStack;

class CourseCrudController extends AbstractCrudController
{
    // Definimos las opciones de grados de educación chilena
    private const CHILEAN_GRADES = [

        '3° Medio' => '3M',
        '4° Medio' => '4M',
    ];



    public function __construct(
        private RequestStack $requestStack,
        private AdminUrlGenerator $adminUrlGenerator
    ) {}

    public static function getEntityFqcn(): string
    {
        return Course::class;
    }


    public function persistEntity(EntityManagerInterface $entityManager, $entity): void
    {

        parent::persistEntity($entityManager, $entity);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entity): void
    {
        parent::updateEntity($entityManager, $entity);
    }

    public function configureFields(string $pageName): iterable
    {

        return [
            TextField::new('name', 'Nombre'),
            TextEditorField::new('description', 'Descripción')->onlyOnForms(),
            IntegerField::new('maxCapacity', 'Cupo máximo'),
            DateTimeField::new('enrollmentDeadline', 'Fecha límite de inscripción')
                ->setHelp('Dejar vacío para permitir inscripciones sin límite de tiempo'),
            BooleanField::new('isActive', 'Activo'),
            AssociationField::new('teacher', 'Profesor')
                ->setCrudController(UserCrudController::class),

            ChoiceField::new('targetGrades', 'Cursos Destinados')
                ->setChoices(self::CHILEAN_GRADES)
                ->allowMultipleChoices(true)
                ->renderAsBadges(),


        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $viewEnrollments = Action::new('viewEnrollments', 'Ver Inscritos', 'fa fa-users')
            ->linkToCrudAction('index', function (Course $course) {
                return $this->adminUrlGenerator
                    ->setController(EnrollmentCrudController::class)
                    ->setAction(Action::INDEX)
                    ->set('filters[course][comparison]', '=')
                    ->set('filters[course][value]', $course->getId())
                    ->generateUrl();
            });

        return $actions
            ->add(Crud::PAGE_INDEX, $viewEnrollments)
            ->reorder(Crud::PAGE_INDEX, ['viewEnrollments', Action::EDIT, Action::DELETE]);
    }
}
