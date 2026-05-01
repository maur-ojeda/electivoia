<?php

namespace App\Controller\Admin;

use App\Entity\Course;
use App\Entity\Enrollment;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;

class EnrollmentCrudController extends AbstractCrudController
{
    public function __construct(private TenantContext $tenantContext) {}

    public function persistEntity(EntityManagerInterface $entityManager, $entity): void
    {
        if ($entity instanceof Enrollment && $entity->getEnrolledAt() === null) {
            $entity->setEnrolledAt(new \DateTime());
        }

        parent::persistEntity($entityManager, $entity);
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof \App\Entity\Enrollment) {
            $course = $entityInstance->getCourse();
            if ($course) {
                $current = $course->getCurrentEnrollment();
                if ($current > 0) {
                    $course->setCurrentEnrollment($current - 1);
                    $entityManager->persist($course);
                }
            }
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        if ($this->tenantContext->hasSchool() && !$this->isGranted('ROLE_SUPER_ADMIN')) {
            $qb->join('entity.course', '_enr_c')
                ->andWhere('_enr_c.school = :_tenant_school')
                ->setParameter('_tenant_school', $this->tenantContext->getCurrentSchool());
        }

        return $qb;
    }

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
            TextField::new('student.rut', 'RUT del Alumno')->onlyOnIndex(),
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
