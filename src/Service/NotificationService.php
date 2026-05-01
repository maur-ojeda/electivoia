<?php

namespace App\Service;

use App\Entity\Course;
use App\Entity\School;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class NotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private LoggerInterface $logger,
        private string $mailerFrom
    ) {}

    public function sendEnrollmentConfirmation(User $student, Course $course): void
    {
        if (!$student->getEmail()) {
            return;
        }

        try {
            $html = $this->twig->render('email/enrollment_confirmation.html.twig', [
                'student' => $student,
                'course' => $course,
                'enrolledAt' => new \DateTime(),
            ]);

            $email = (new Email())
                ->from($this->mailerFrom)
                ->to($student->getEmail())
                ->subject('Inscripción confirmada: ' . $course->getName())
                ->html($html);

            $this->mailer->send($email);
        } catch (\Exception $e) {
            $this->logger->warning('No se pudo enviar email de confirmación: ' . $e->getMessage());
        }
    }

    public function sendEnrollmentDisplaced(User $displaced, User $displacer, Course $course): void
    {
        if (!$displaced->getEmail()) {
            return;
        }

        try {
            $html = $this->twig->render('email/enrollment_displaced.html.twig', [
                'student' => $displaced,
                'course' => $course,
            ]);

            $email = (new Email())
                ->from($this->mailerFrom)
                ->to($displaced->getEmail())
                ->subject('Has sido desplazado del curso: ' . $course->getName())
                ->html($html);

            $this->mailer->send($email);
        } catch (\Exception $e) {
            $this->logger->warning('No se pudo enviar email de desplazamiento: ' . $e->getMessage());
        }
    }

    public function sendSchoolWelcome(User $admin, School $school, string $plainPassword): void
    {
        if (!$admin->getEmail()) {
            return;
        }

        try {
            $html = $this->twig->render('email/school_welcome.html.twig', [
                'admin'         => $admin,
                'school'        => $school,
                'plainPassword' => $plainPassword,
            ]);

            $email = (new Email())
                ->from($this->mailerFrom)
                ->to($admin->getEmail())
                ->subject('Bienvenido a ElectivoIA — ' . $school->getName())
                ->html($html);

            $this->mailer->send($email);
        } catch (\Exception $e) {
            $this->logger->warning('No se pudo enviar email de bienvenida: ' . $e->getMessage());
        }
    }

    public function sendBulkAnnouncement(array $students, string $subject, string $body): int
    {
        $sent = 0;
        foreach ($students as $student) {
            if (!$student->getEmail()) {
                continue;
            }
            try {
                $html = $this->twig->render('email/announcement.html.twig', [
                    'student' => $student,
                    'subject' => $subject,
                    'body' => $body,
                ]);

                $email = (new Email())
                    ->from($this->mailerFrom)
                    ->to($student->getEmail())
                    ->subject($subject)
                    ->html($html);

                $this->mailer->send($email);
                $sent++;
            } catch (\Exception $e) {
                $this->logger->warning('No se pudo enviar email a ' . $student->getEmail() . ': ' . $e->getMessage());
            }
        }
        return $sent;
    }
}
