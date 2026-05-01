<?php

namespace App\Controller\Admin;

use App\Entity\Attendance;
use App\Entity\Course;
use App\Entity\Enrollment;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

class CourseCrudController extends AbstractCrudController
{
    // Definimos las opciones de grados de educación chilena
    private const CHILEAN_GRADES = [

        '3° Medio' => '3M',
        '4° Medio' => '4M',
    ];



    public function __construct(
        private AdminUrlGenerator $adminUrlGenerator,
        private TenantContext $tenantContext,
        private RouterInterface $router,
    ) {}

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        if ($this->tenantContext->hasSchool() && !$this->isGranted('ROLE_SUPER_ADMIN')) {
            $qb->andWhere('entity.school = :_tenant_school')
                ->setParameter('_tenant_school', $this->tenantContext->getCurrentSchool());
        }

        return $qb;
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entity): void
    {
        if ($entity instanceof Course && $entity->getSchool() === null && $this->tenantContext->hasSchool()) {
            $entity->setSchool($this->tenantContext->getCurrentSchool());
        }

        parent::persistEntity($entityManager, $entity);
    }

    public static function getEntityFqcn(): string
    {
        return Course::class;
    }


    public function updateEntity(EntityManagerInterface $entityManager, $entity): void
    {
        parent::updateEntity($entityManager, $entity);
    }

    public function delete(AdminContext $context): Response
    {
        $entityInstance = $context->getEntity()->getInstance();

        if ($entityInstance instanceof Course) {
            $em = $this->container->get('doctrine')->getManager();
            $enrollmentCount = $em->getRepository(Enrollment::class)->count(['course' => $entityInstance]);
            $attendanceCount = $em->getRepository(Attendance::class)->count(['course' => $entityInstance]);

            if ($enrollmentCount > 0 || $attendanceCount > 0) {
                $parts = [];
                if ($enrollmentCount > 0) {
                    $parts[] = "{$enrollmentCount} inscripción(es) activa(s)";
                }
                if ($attendanceCount > 0) {
                    $parts[] = "{$attendanceCount} registro(s) de asistencia";
                }
                $detail = implode(' y ', $parts);

                $this->addFlash('danger', "No puedes eliminar este curso porque tiene {$detail}.");

                $url = $this->adminUrlGenerator
                    ->setAction(Action::INDEX)
                    ->generateUrl();

                return $this->redirect($url);
            }
        }

        return parent::delete($context);
    }

    public function configureFields(string $pageName): iterable
    {

        return [
            TextField::new('name', 'Nombre'),
            TextField::new('schedule', 'Horario')->setRequired(false)->setHelp('Ej: Lunes y Miércoles 15:30-17:00'),
            TextEditorField::new('description', 'Descripción')->onlyOnForms(),
            TextField::new('enrollmentInfo', 'Cupo')
                ->onlyOnIndex()
                ->formatValue(function ($value, $entity) {
                    if (!$entity || $entity->getMaxCapacity() === null || $entity->getMaxCapacity() === 0) {
                        return '<span class="badge bg-secondary">Sin cupo definido</span>';
                    }
                    $current = (int) ($entity->getCurrentEnrollment() ?? 0);
                    $max = (int) $entity->getMaxCapacity();
                    $pct = ($current / $max) * 100;
                    $color = match(true) {
                        $pct >= 80 => 'danger',
                        $pct >= 50 => 'warning',
                        default => 'success',
                    };
                    return sprintf(
                        '<span class="badge bg-%s">%d/%d alumnos (%.0f%%)</span>',
                        $color, $current, $max, $pct
                    );
                })
                ->renderAsHtml(),
            IntegerField::new('maxCapacity', 'Cupo máximo')->onlyOnForms(),
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
            ->linkToUrl(function (Course $course) {
                return $this->adminUrlGenerator
                    ->setController(EnrollmentCrudController::class)
                    ->setAction(Action::INDEX)
                    ->set('filters[course][comparison]', '=')
                    ->set('filters[course][value]', $course->getId())
                    ->generateUrl();
            });

        $exportPdf = Action::new('exportPdf', 'Exportar PDF', 'fa fa-file-pdf')
            ->linkToUrl(function (Course $course) {
                return $this->generateExportUrl($course, 'pdf');
            });

        $exportExcel = Action::new('exportExcel', 'Exportar Excel', 'fa fa-file-excel')
            ->linkToUrl(function (Course $course) {
                return $this->generateExportUrl($course, 'xlsx');
            });

        return $actions
            ->add(Crud::PAGE_INDEX, $viewEnrollments)
            ->add(Crud::PAGE_INDEX, $exportPdf)
            ->add(Crud::PAGE_INDEX, $exportExcel)
            ->add(Crud::PAGE_DETAIL, $exportPdf)
            ->add(Crud::PAGE_DETAIL, $exportExcel)
            ->reorder(Crud::PAGE_INDEX, ['viewEnrollments', 'exportPdf', 'exportExcel', Action::EDIT, Action::DELETE]);
    }

    private function generateExportUrl(Course $course, string $format): string
    {
        return $this->router->generate('admin_export_students', [
            'id' => $course->getId(),
            '_format' => $format,
        ]);
    }
}
