<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/guardian', name: 'guardian_')]
#[IsGranted('ROLE_GUARDIAN')]
class GuardianController extends AbstractController
{
    #[Route('/dashboard', name: 'dashboard')]
    public function dashboard(EntityManagerInterface $em): Response
    {
        $guardian = $this->getUser();
        $pupils = $guardian->getGuardianStudents();

        foreach ($pupils as $pupil) {
            $enrollments = $em->getRepository(\App\Entity\Enrollment::class)
                ->findBy(['student' => $pupil]);
            $pupilEnrollments[$pupil->getId()] = $enrollments;
        }

        return $this->render('guardian/dashboard.html.twig', [
            'pupils' => $pupils,
            'pupilEnrollments' => $pupilEnrollments, // ← Pasar los datos separadamente
        ]);
    }
}
