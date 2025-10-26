<?php

namespace App\Controller\Admin;

// Importaciones necesarias adicionales
use App\Entity\User;
use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\InterestProfile;


use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
        yield MenuItem::linkToCrud('Usuarios', 'fa fa-user', User::class);
        yield MenuItem::linkToCrud('Cursos', 'fa fa-book', Course::class);
        yield MenuItem::linkToCrud('Inscripciones', 'fa fa-list', Enrollment::class);
        yield MenuItem::linkToCrud('Perfiles IA', 'fa fa-brain', InterestProfile::class);
    }
}
