<?php

namespace App\Controller\Admin;

// Importaciones necesarias adicionales
use App\Entity\User;
use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\InterestProfile;
use App\Controller\Admin\UserCrudController;


use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface; // Importar para generar rutas
use Symfony\Component\Security\Http\Attribute\IsGranted;
use EasyCorp\Bundle\EasyAdminBundle\Filter\Filter;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;


#[IsGranted('ROLE_ADMIN')]
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);

        return $this->redirect($adminUrlGenerator->setController(\App\Controller\Admin\UserCrudController::class)->generateUrl());
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Electivoia');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');

        // --- Menú para Usuarios ---
        yield MenuItem::section('Usuarios');
        yield MenuItem::linkToCrud('Usuarios', 'fas fa-users', User::class)
            ->setController(UserCrudController::class);

        // --- Otros ítems ---
        yield MenuItem::section('Cursos');
        yield MenuItem::linkToCrud('Cursos', 'fas fa-book', Course::class);

        yield MenuItem::section('Inscripciones');
        yield MenuItem::linkToCrud('Inscripciones', 'fas fa-edit', Enrollment::class);

        // Opcional: Otros perfiles o entidades
        yield MenuItem::section('Otros');
        yield MenuItem::linkToCrud('Perfiles IA', 'fa fa-brain', InterestProfile::class);

        yield MenuItem::section('Informes');
        yield MenuItem::linkToUrl('📊 Reportes Avanzados', 'fa fa-chart-line', $this->generateUrl('admin_reports_index'));
    }
}
