<?php

namespace App\Controller;

use App\Entity\Attendance;
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

        $pupilEnrollments = [];
        $pupilAttendance = [];

        foreach ($pupils as $pupil) {
            $enrollments = $em->getRepository(\App\Entity\Enrollment::class)
                ->findBy(['student' => $pupil]);
            $pupilEnrollments[$pupil->getId()] = $enrollments;

            $attendanceData = [];
            foreach ($enrollments as $enrollment) {
                $course = $enrollment->getCourse();
                $records = $em->getRepository(Attendance::class)->findBy(
                    ['student' => $pupil, 'course' => $course],
                    ['date' => 'DESC']
                );
                $total = count($records);
                $attended = count(array_filter($records, fn($a) => in_array($a->getStatus(), ['present', 'justified'])));
                $percentage = $total > 0 ? round($attended / $total * 100) : null;

                $attendanceData[$course->getId()] = [
                    'records' => $records,
                    'total' => $total,
                    'attended' => $attended,
                    'percentage' => $percentage,
                ];
            }
            $pupilAttendance[$pupil->getId()] = $attendanceData;
        }

        return $this->render('guardian/dashboard.html.twig', [
            'pupils' => $pupils,
            'pupilEnrollments' => $pupilEnrollments,
            'pupilAttendance' => $pupilAttendance,
        ]);
    }
}
