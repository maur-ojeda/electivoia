<?php

namespace App\Controller;

use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/enrollment-period', name: 'admin_enrollment_period')]
#[IsGranted('ROLE_ADMIN')]
class AdminEnrollmentPeriodController extends AbstractController
{
    public function __construct(private TenantContext $tenantContext) {}

    #[Route('', name: '', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $school = $this->tenantContext->getCurrentSchool();

        if ($school === null) {
            $this->addFlash('error', 'No hay colegio asignado a tu cuenta.');
            return $this->redirectToRoute('admin_dashboard');
        }

        if ($request->isMethod('POST')) {
            $startRaw = $request->request->get('enrollmentStart');
            $endRaw   = $request->request->get('enrollmentEnd');

            $school->setEnrollmentStart($startRaw ? new \DateTimeImmutable($startRaw) : null);
            $school->setEnrollmentEnd($endRaw   ? new \DateTimeImmutable($endRaw)   : null);

            $em->flush();
            $this->addFlash('success', 'Período de inscripción actualizado.');
            return $this->redirectToRoute('admin_enrollment_period');
        }

        return $this->render('admin/enrollment_period.html.twig', [
            'school' => $school,
        ]);
    }
}
