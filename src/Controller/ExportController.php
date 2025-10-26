<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Enrollment;
use App\Service\EnrollmentService;
use App\Entity\InterestProfile;
use App\Service\RecommendationService; //<-ya está agregado
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\StreamedResponse;



#[Route('/admin/export')]
class ExportController extends AbstractController
{
    #[Route('/export/course/{id}/students.xlsx', name: 'admin_export_students')]
    #[IsGranted('ROLE_TEACHER')]
    public function exportStudents(Course $course, EntityManagerInterface $em): StreamedResponse
    {
        // Verificar que el curso pertenezca al profesor
        if ($course->getTeacher() !== $this->getUser()) {
            throw $this->createAccessDeniedException('No tienes permiso para exportar este curso.');
        }

        $response = new StreamedResponse();
        $response->setCallback(function () use ($course, $em) {
            $enrollments = $em->getRepository(Enrollment::class)->findBy(['course' => $course]);

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setCellValue('A1', 'Email');
            $sheet->setCellValue('B1', 'Grado');
            $sheet->setCellValue('C1', 'Promedio');

            $row = 2;
            foreach ($enrollments as $enrollment) {
                $student = $enrollment->getStudent();
                $sheet->setCellValue('A' . $row, $student->getEmail());
                $sheet->setCellValue('B' . $row, $student->getGrade());
                $sheet->setCellValue('C' . $row, $student->getAverageGrade());
                $row++;
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $filename = 'alumnos_' . $course->getName() . '.xlsx';
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment;filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }
}
