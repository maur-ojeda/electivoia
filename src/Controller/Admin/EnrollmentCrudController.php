<?php

namespace App\Controller\Admin;

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
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Entity\User;
use Symfony\Component\Routing\RouterInterface;

class EnrollmentCrudController extends AbstractCrudController
{
    public function __construct(
        private TenantContext $tenantContext,
        private AdminUrlGenerator $adminUrlGenerator,
        private RequestStack $requestStack,
        private RouterInterface $router,
    ) {}

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
            // Index: columnas de solo lectura para mostrar datos legibles
            TextField::new('student.fullName', 'Nombre Alumno')->onlyOnIndex(),
            TextField::new('student.rut', 'RUT del Alumno')->onlyOnIndex(),
            TextField::new('course.name', 'Curso')->onlyOnIndex(),

            // Forms: campos de asociación para seleccionar entidades
            AssociationField::new('student', 'Alumno')
                ->onlyOnForms()
                ->setFormTypeOption('query_builder', fn ($repo) => $repo->createQueryBuilder('u')
                    ->where('json_array_contains(u.roles, :role) = true')
                    ->setParameter('role', 'ROLE_STUDENT')
                    ->orderBy('u.fullName', 'ASC'))
                ->setFormTypeOption('choice_label', function (?User $user) {
                    if ($user === null) {
                        return '';
                    }
                    return sprintf('%s (%s)', $user->getFullName() ?? 'Sin nombre', $user->getRut() ?? 'Sin RUT');
                }),
            AssociationField::new('course', 'Curso Inscrito')->onlyOnForms(),

            DateTimeField::new('enrolledAt', 'Fecha de Inscripción')
                ->setFormat('dd/MM/yyyy HH:mm')
                ->onlyOnIndex(),
        ];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('course', 'Curso'))
            ->add(EntityFilter::new('student', 'Alumno'));
    }

    public function configureActions(Actions $actions): Actions
    {
        $courseId = $this->getCourseFilterId();

        if ($courseId !== null) {
            $exportPdf = Action::new('exportEnrollmentsPdf', 'Exportar PDF', 'fa fa-file-pdf')
                ->linkToUrl(fn () => $this->generateExportUrl($courseId, 'pdf'));

            $exportExcel = Action::new('exportEnrollmentsExcel', 'Exportar Excel', 'fa fa-file-excel')
                ->linkToUrl(fn () => $this->generateExportUrl($courseId, 'xlsx'));

            $actions = $actions
                ->add(Crud::PAGE_INDEX, $exportPdf)
                ->add(Crud::PAGE_INDEX, $exportExcel);
        }

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

    private function getCourseFilterId(): ?int
    {
        $request = $this->requestStack->getCurrentRequest();
        $filters = $request->query->all('filters');

        return isset($filters['course']['value']) ? (int) $filters['course']['value'] : null;
    }

    private function generateExportUrl(int $courseId, string $format): string
    {
        return $this->router->generate('admin_export_students', [
            'id' => $courseId,
            '_format' => $format,
        ]);
    }

    public function exportEnrollments(): \Symfony\Component\HttpFoundation\Response
    {
        $courseId = $this->getCourseFilterId();

        if ($courseId === null) {
            throw $this->createNotFoundException('Seleccioná un curso para exportar.');
        }

        return $this->redirect($this->generateExportUrl($courseId, $this->requestStack->getCurrentRequest()->query->get('format', 'xlsx')));
    }
}
