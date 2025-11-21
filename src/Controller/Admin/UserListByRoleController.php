<?php

namespace App\Controller\Admin;

// Importa AbstractController en lugar de AbstractCrudController
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController; // O AbstractController
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

// Extiende de AbstractController
class UserListByRoleController extends AbstractController
{
    private AdminUrlGenerator $adminUrlGenerator;

    public function __construct(AdminUrlGenerator $adminUrlGenerator)
    {
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    #[Route('/admin/users-by-role', name: 'admin_users_by_role')] // Opcional: protege con #[IsGranted('ROLE_ADMIN')]
    public function index(EntityManagerInterface $em, Request $request): RedirectResponse
    {
        $role = $request->query->get('role', '');

        if (!in_array($role, ['ROLE_STUDENT', 'ROLE_TEACHER'])) {
            $url = $this->adminUrlGenerator
                ->setController(UserCrudController::class)
                ->setAction('index')
                ->generateUrl();

            return $this->redirect($url);
        }

        // Genera una URL pre-filtrada para el CRUD de usuarios en EasyAdmin
        $url = $this->adminUrlGenerator
            ->setController(UserCrudController::class)
            ->setAction('index')
            ->set('filters[roles][comparison]', 'contains')
            ->set('filters[roles][value]', $role)
            ->generateUrl();

        return $this->redirect($url);
    }

    // Este método no se usa para la funcionalidad de redirección, pero AbstractCrudController lo espera.
    // Si extiendes de AbstractController directamente, no necesitas definirlo a menos que lo uses.
    // public function configureFields(string $pageName): iterable
    // {
    //     // No necesario si solo rediriges
    //     return [];
    // }
}
