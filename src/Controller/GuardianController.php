<?php


namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/guardian', name: 'guardian_')]
#[IsGranted('ROLE_GUARDIAN')]
class GuardianController extends AbstractController
{
    #[Route('/dashboard', name: 'dashboard')]
    public function dashboard(): Response
    {
        $guardian = $this->getUser();
        $pupils = $guardian->getGuardianStudents();

        return $this->render('guardian/dashboard.html.twig', [
            'pupils' => $pupils,
        ]);
    }
}
