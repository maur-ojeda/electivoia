<?php

namespace App\Controller\Admin;

use App\Entity\CourseCategory;
use App\Entity\School;
use App\Entity\User;
use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\InterestProfile;
use App\Controller\Admin\CourseCategoryCrudController;
use App\Controller\Admin\UserCrudController;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        // Old EasyAdmin URL format (?crudAction=&crudControllerFqcn=) passes
        // action/controller as query params instead of route params; EasyAdmin
        // can't resolve entity context and throws TypeError. Return 404 early.
        $request = $this->container->get('request_stack')->getCurrentRequest();
        if ($request !== null && ($request->query->has('crudAction') || $request->query->has('crudControllerFqcn'))) {
            throw new NotFoundHttpException('Legacy EasyAdmin URL format is not supported.');
        }

        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);

        return $this->redirect($adminUrlGenerator->setController(UserCrudController::class)->generateUrl());
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()->setTitle('ElectivoIA');
    }

    public function configureAssets(): Assets
    {
        return Assets::new()->addWebpackEncoreEntry('admin_csrf');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');

        yield MenuItem::section('Operación');
        yield MenuItem::linkToCrud('Cursos', 'fas fa-book', Course::class);
        yield MenuItem::linkToCrud('Inscripciones', 'fas fa-edit', Enrollment::class);
        yield MenuItem::linkToUrl('Período inscripción', 'fa fa-calendar', $this->generateUrl('admin_enrollment_period'));
        yield MenuItem::linkToUrl('Inscripción masiva', 'fa fa-list-check', $this->generateUrl('admin_enrollments_bulk'));

        yield MenuItem::section('Reportes');
        yield MenuItem::linkToUrl('Reportes avanzados', 'fa fa-chart-line', $this->generateUrl('admin_reports_index'));
        yield MenuItem::linkToUrl('Panel IA', 'fa fa-robot', $this->generateUrl('admin_ai_dashboard'));

        yield MenuItem::section('Usuarios');
        yield MenuItem::linkToCrud('Todos los usuarios', 'fas fa-users', User::class)
            ->setController(UserCrudController::class);
        yield MenuItem::linkToCrud('Perfiles IA', 'fa fa-brain', InterestProfile::class);

        yield MenuItem::section('Comunicación');
        yield MenuItem::linkToUrl('Email blast', 'fa fa-envelope', $this->generateUrl('admin_email_blast'));
        yield MenuItem::linkToUrl('Importar CSV', 'fa fa-upload', $this->generateUrl('admin_users_import'));

        yield MenuItem::section('Configuración');
        yield MenuItem::linkToCrud('Categorías', 'fa fa-tag', CourseCategory::class)
            ->setController(CourseCategoryCrudController::class);

        // Super Admin only
        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            yield MenuItem::section('Super Admin');
            yield MenuItem::linkToCrud('Colegios', 'fas fa-school', School::class)
                ->setController(SchoolCrudController::class);
            yield MenuItem::linkToUrl('Nuevo colegio', 'fas fa-plus-circle', $this->generateUrl('super_admin_onboard'));
            yield MenuItem::linkToUrl('Panel global', 'fas fa-globe', $this->generateUrl('super_admin_dashboard'));
        }
    }
}
