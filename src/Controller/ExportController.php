<?php

namespace App\Controller;

use App\Entity\Attendance;
use App\Entity\Course;
use App\Entity\Enrollment;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\ExpressionLanguage\Expression;


#[Route('/admin/export')]
class ExportController extends AbstractController
{
    #[Route('/attendance', name: 'admin_export_attendance')]
    #[IsGranted('ROLE_ADMIN')]
    public function exportAttendance(EntityManagerInterface $em): StreamedResponse
    {
        $attendances = $em->createQueryBuilder()
            ->select('a, s, c, t')
            ->from(Attendance::class, 'a')
            ->join('a.student', 's')
            ->join('a.course', 'c')
            ->join('c.teacher', 't')
            ->orderBy('c.name', 'ASC')
            ->addOrderBy('s.fullName', 'ASC')
            ->addOrderBy('a.date', 'ASC')
            ->getQuery()
            ->getResult();

        $response = new StreamedResponse();
        $response->setCallback(function () use ($attendances) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setCellValue('A1', 'RUT');
            $sheet->setCellValue('B1', 'Nombre');
            $sheet->setCellValue('C1', 'Grado');
            $sheet->setCellValue('D1', 'Curso');
            $sheet->setCellValue('E1', 'Profesor');
            $sheet->setCellValue('F1', 'Fecha');
            $sheet->setCellValue('G1', 'Estado');

            $row = 2;
            foreach ($attendances as $att) {
                $student = $att->getStudent();
                $course = $att->getCourse();
                $sheet->setCellValue('A' . $row, $student->getRut());
                $sheet->setCellValue('B' . $row, $student->getFullName());
                $sheet->setCellValue('C' . $row, $student->getGrade());
                $sheet->setCellValue('D' . $row, $course->getName());
                $sheet->setCellValue('E' . $row, $course->getTeacher()->getFullName());
                $sheet->setCellValue('F' . $row, $att->getDate()->format('d/m/Y'));
                $sheet->setCellValue('G' . $row, $att->getStatus());
                $row++;
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $filename = 'asistencias_' . date('Y-m-d') . '.xlsx';
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment;filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    #[Route('/export/course/{id}/students.xlsx', name: 'admin_export_students')]
    #[IsGranted(new Expression('is_granted("ROLE_TEACHER") or is_granted("ROLE_ADMIN")'))]
    public function exportStudents(Course $course, EntityManagerInterface $em): StreamedResponse
    {

        $currentUser = $this->getUser();

        if (!$this->isGranted('ROLE_ADMIN') && $course->getTeacher() !== $currentUser) {
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
