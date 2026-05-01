<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\NotificationService;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/email', name: 'admin_email_')]
#[IsGranted('ROLE_ADMIN')]
class AdminEmailController extends AbstractController
{
    public function __construct(private TenantContext $tenantContext) {}

    #[Route('/blast', name: 'blast')]
    public function blast(Request $request, EntityManagerInterface $em, NotificationService $notificationService): Response
    {
        $grades = ['3M', '4M'];
        $sent = null;
        $recipientCount = 0;
        $selectedGrade = null;

        if ($request->isMethod('POST')) {
            $selectedGrade = $request->request->get('grade');
            $subject = trim($request->request->get('subject', ''));
            $body = trim($request->request->get('body', ''));

            $errors = [];
            if (!$subject) {
                $errors[] = 'El asunto es obligatorio.';
            }
            if (!$body) {
                $errors[] = 'El mensaje es obligatorio.';
            }

            if (empty($errors)) {
                $qb = $em->createQueryBuilder()
                    ->select('u')
                    ->from(User::class, 'u')
                    ->where('u.active = true')
                    ->andWhere('u.email IS NOT NULL');

                if ($this->tenantContext->hasSchool()) {
                    $qb->andWhere('u.school = :_school')
                       ->setParameter('_school', $this->tenantContext->getCurrentSchool());
                }

                if ($selectedGrade) {
                    $qb->andWhere('u.grade = :grade')->setParameter('grade', $selectedGrade);
                }

                // Filtrar solo estudiantes
                $students = array_filter(
                    $qb->getQuery()->getResult(),
                    fn(User $u) => in_array('ROLE_STUDENT', $u->getRoles())
                );

                $recipientCount = count($students);
                $sent = $notificationService->sendBulkAnnouncement(array_values($students), $subject, $body);
                $this->addFlash('success', "Email enviado a {$sent} de {$recipientCount} estudiantes con email registrado.");
                return $this->redirectToRoute('admin_email_blast');
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        // Preview: contar destinatarios por grado (filtrado por tenant)
        $gradeStats = [];
        foreach ($grades as $grade) {
            $qb = $em->createQueryBuilder()
                ->select('COUNT(u.id)')
                ->from(User::class, 'u')
                ->where('u.grade = :grade')
                ->andWhere('u.active = true')
                ->andWhere('u.email IS NOT NULL')
                ->setParameter('grade', $grade);
            if ($this->tenantContext->hasSchool()) {
                $qb->andWhere('u.school = :_school')
                   ->setParameter('_school', $this->tenantContext->getCurrentSchool());
            }
            $gradeStats[$grade] = (int) $qb->getQuery()->getSingleScalarResult();
        }

        $totalQb = $em->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->where('u.active = true')
            ->andWhere('u.email IS NOT NULL');
        if ($this->tenantContext->hasSchool()) {
            $totalQb->andWhere('u.school = :_school')
                    ->setParameter('_school', $this->tenantContext->getCurrentSchool());
        }
        $totalWithEmail = $totalQb->getQuery()->getSingleScalarResult();

        return $this->render('admin/email_blast.html.twig', [
            'grades' => $grades,
            'gradeStats' => $gradeStats,
            'totalWithEmail' => (int) $totalWithEmail,
        ]);
    }
}
