<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

class MagicLinkController extends AbstractController
{
    #[Route('/magic-login', name: 'magic_login', methods: ['GET', 'POST'])]
    public function requestLink(
        Request $request,
        UserRepository $userRepository,
        LoginLinkHandlerInterface $loginLinkHandler,
        MailerInterface $mailer,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $sent = false;
        $error = null;

        if ($request->isMethod('POST')) {
            $rut = trim($request->request->get('rut', ''));

            $user = $userRepository->findOneBy(['rut' => $rut]);

            // Always show "sent" to prevent RUT enumeration, even on error
            if ($user && $user->getEmail()) {
                try {
                    $loginLink = $loginLinkHandler->createLoginLink($user);

                    $email = (new TemplatedEmail())
                        ->to($user->getEmail())
                        ->subject('Tu enlace de acceso a ElectivoIA')
                        ->htmlTemplate('email/magic_link.html.twig')
                        ->context([
                            'user' => $user,
                            'loginLink' => $loginLink,
                            'expiry' => 10,
                        ]);

                    $mailer->send($email);
                } catch (\Throwable $e) {
                    // Log silently — don't reveal failure to user
                }
            }

            $sent = true;
        }

        return $this->render('security/magic_login.html.twig', [
            'sent' => $sent,
            'error' => $error,
        ]);
    }

    /**
     * This route is intercepted by Symfony's login_link authenticator.
     * The method body is never reached on a valid link.
     */
    #[Route('/login/link-check', name: 'login_link_check')]
    public function check(): Response
    {
        // Symfony intercepts valid links before reaching here.
        // Reaching here means the link was invalid or expired.
        $this->addFlash('error', 'El enlace de acceso ha expirado o ya fue utilizado. Solicita uno nuevo.');
        return $this->redirectToRoute('magic_login');
    }
}
