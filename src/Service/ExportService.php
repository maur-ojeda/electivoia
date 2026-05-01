<?php

namespace App\Service;

use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\School;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Twig\Environment;

class ExportService
{
    public function __construct(
        private EntityManagerInterface $em,
        private TenantContext $tenantContext,
        private Environment $twig,
    ) {}

    public static function getStudentColumnDefinitions(): array
    {
        return [
            ['key' => '#', 'label' => '#', 'accessor' => fn(array $row, int $idx) => $idx + 1],
            ['key' => 'rut', 'label' => 'RUT', 'accessor' => fn(array $row) => $row['rut'] ?? ''],
            ['key' => 'fullName', 'label' => 'Nombre Completo', 'accessor' => fn(array $row) => $row['fullName'] ?? ''],
            ['key' => 'email', 'label' => 'Email', 'accessor' => fn(array $row) => $row['email'] ?? ''],
            ['key' => 'courseName', 'label' => 'Curso', 'accessor' => fn(array $row) => $row['courseName'] ?? ''],
            ['key' => 'averageGrade', 'label' => 'Promedio', 'accessor' => fn(array $row) => $row['averageGrade'] ?? ''],
            ['key' => 'enrolledAt', 'label' => 'Fecha Inscripción', 'accessor' => fn(array $row) => $row['enrolledAt'] ?? ''],
        ];
    }

    public function getStudentExportData(Course $course): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('e, s')
            ->from(Enrollment::class, 'e')
            ->join('e.student', 's')
            ->where('e.course = :course')
            ->setParameter('course', $course)
            ->orderBy('s.fullName', 'ASC');

        if ($this->tenantContext->hasSchool() && !$this->isSuperAdmin()) {
            $qb->join('e.course', 'c')
               ->andWhere('c.school = :school')
               ->setParameter('school', $this->tenantContext->getCurrentSchool());
        }

        $enrollments = $qb->getQuery()->getResult();

        $data = [];
        foreach ($enrollments as $enrollment) {
            $student = $enrollment->getStudent();
            $data[] = [
                'rut' => $student->getRut() ?? '',
                'fullName' => $student->getFullName() ?? '',
                'email' => $student->getEmail() ?? '',
                'courseName' => $course->getName(),
                'averageGrade' => $student->getAverageGrade() !== null ? (string) $student->getAverageGrade() : '',
                'enrolledAt' => $enrollment->getEnrolledAt() !== null
                    ? $enrollment->getEnrolledAt()->format('d/m/Y')
                    : '',
            ];
        }

        return $data;
    }

    public function generateStudentExcel(array $data, string $filename): StreamedResponse
    {
        $columns = self::getStudentColumnDefinitions();

        $response = new StreamedResponse();
        $response->setCallback(function () use ($data, $columns): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Alumnos');

            $col = 1;
            foreach ($columns as $column) {
                $sheet->setCellValue([$col, 1], $column['label']);
                $col++;
            }

            $row = 2;
            foreach ($data as $rowIndex => $record) {
                $col = 1;
                foreach ($columns as $column) {
                    $value = ($column['accessor'])($record, $rowIndex);
                    $sheet->setCellValue([$col, $row], $value);
                    $col++;
                }
                $row++;
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment;filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    public function generateStudentPdf(array $data, Course $course, ?School $school): StreamedResponse
    {
        $columns = self::getStudentColumnDefinitions();

        $html = $this->twig->render('export/students_pdf.html.twig', [
            'columns' => $columns,
            'data' => $data,
            'course' => $course,
            'school' => $school,
            'exportDate' => date('d/m/Y H:i'),
        ]);

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $output = $dompdf->output();

        $filename = 'alumnos_' . $course->getName() . '.pdf';

        $response = new StreamedResponse(function () use ($output): void {
            echo $output;
        });

        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', 'attachment;filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    private function isSuperAdmin(): bool
    {
        return $this->tenantContext->hasSchool() === false;
    }
}