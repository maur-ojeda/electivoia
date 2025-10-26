<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Security;

/**
 * Redirige al usuario a una página específica después de iniciar sesión con éxito,
 * basándose en su rol.
 */
class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    private RouterInterface $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    /**
     * Se llama cuando un intento de autenticación interactiva tiene éxito.
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        /** @var \App\Entity\User $user */
        $user = $token->getUser();
        $roles = $user->getRoles();

        $targetUrl = '/'; // Ruta por defecto si no se encuentra ningún rol específico

        // 1. Prioridad: ROLE_ADMIN
        if (in_array('ROLE_ADMIN', $roles, true)) {
            // Asegúrate de que esta ruta exista en tu archivo config/routes.yaml
            $targetUrl = $this->router->generate('admin');

            // 2. Prioridad: ROLE_TEACHER
        } elseif (in_array('ROLE_TEACHER', $roles, true)) {
            // Asegúrate de que esta ruta exista
            $targetUrl = $this->router->generate('teacher_courses');

            // 3. Prioridad: ROLE_STUDENT
        } elseif (in_array('ROLE_STUDENT', $roles, true)) {
            // Asegúrate de que esta ruta exista
            $targetUrl = $this->router->generate('student_courses');

            // 4. Prioridad: ROLE_GUARDIAN
        } elseif (in_array('ROLE_GUARDIAN', $roles, true)) {
            // Asegúrate de que esta ruta exista
            $targetUrl = $this->router->generate('guardian_dashboard');
        }

        // Redirigir a la URL determinada
        return new RedirectResponse($targetUrl);
    }
}
