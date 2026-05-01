<?php

namespace App\Controller;

use App\Entity\Attendance;
use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\User;
use App\Service\ExportService;
use App\Service\TenantContext;
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
    public function __construct(
        private TenantContext $tenantContext,
        private ExportService $exportService,
    ) {}

    #[Route('/attendance', name: 'admin_export_attendance')]
    #[IsGranted('ROLE_ADMIN')]
    public function exportAttendance(EntityManagerInterface $em): StreamedResponse
    {
        $qb = $em->createQueryBuilder()
            ->select('a, s, c, t')
            ->from(Attendance::class, 'a')
            ->join('a.student', 's')
            ->join('a.course', 'c')
            ->join('c.teacher', 't')
            ->orderBy('c.name', 'ASC')
            ->addOrderBy('s.fullName', 'ASC')
            ->addOrderBy('a.date', 'ASC');

        if ($this->tenantContext->hasSchool()) {
            $qb->andWhere('c.school = :_school')
               ->setParameter('_school', $this->tenantContext->getCurrentSchool());
        }

        $attendances = $qb->getQuery()->getResult();

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

    #[Route('/course/{id}/students.{_format}', name: 'admin_export_students', requirements: ['_format' => 'xlsx|pdf'])]
    #[IsGranted(new Expression('is_granted("ROLE_TEACHER") or is_granted("ROLE_ADMIN")'))]
    public function exportStudents(Course $course, string $_format): StreamedResponse
    {
        $currentUser = $this->getUser();

        if (!$this->isGranted('ROLE_ADMIN') && $course->getTeacher() !== $currentUser) {
            throw $this->createAccessDeniedException('No tienes permiso para exportar este curso.');
        }

        if ($this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_SUPER_ADMIN')
            && $this->tenantContext->hasSchool()
            && $course->getSchool()?->getId() !== $this->tenantContext->getCurrentSchool()->getId()
        ) {
            throw $this->createAccessDeniedException('Este curso no pertenece a tu colegio.');
        }

        $data = $this->exportService->getStudentExportData($course);
        $school = $this->tenantContext->hasSchool() ? $this->tenantContext->getCurrentSchool() : $course->getSchool();

        if ($_format === 'pdf') {
            return $this->exportService->generateStudentPdf($data, $course, $school);
        }

        $filename = 'alumnos_' . $course->getName() . '.xlsx';
        return $this->exportService->generateStudentExcel($data, $filename);
    }

    // ── US-305: Export all users ─────────────────────────────────────────────

    #[Route('/users.xlsx', name: 'admin_export_users')]
    #[IsGranted('ROLE_ADMIN')]
    public function exportUsers(EntityManagerInterface $em): StreamedResponse
    {
        $qb = $em->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->orderBy('u.fullName', 'ASC');

        if ($this->tenantContext->hasSchool()) {
            $qb->andWhere('u.school = :_school')
               ->setParameter('_school', $this->tenantContext->getCurrentSchool());
        }

        $users = $qb->getQuery()->getResult();

        $response = new StreamedResponse(function () use ($users) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Usuarios');
            $sheet->setCellValue('A1', 'RUT');
            $sheet->setCellValue('B1', 'Nombre');
            $sheet->setCellValue('C1', 'Email');
            $sheet->setCellValue('D1', 'Roles');
            $sheet->setCellValue('E1', 'Grado');
            $sheet->setCellValue('F1', 'Promedio');
            $sheet->setCellValue('G1', 'Activo');

            $row = 2;
            foreach ($users as $user) {
                $sheet->setCellValue('A' . $row, $user->getRut());
                $sheet->setCellValue('B' . $row, $user->getFullName());
                $sheet->setCellValue('C' . $row, $user->getEmail() ?? '');
                $sheet->setCellValue('D' . $row, implode(', ', $user->getRoles()));
                $sheet->setCellValue('E' . $row, $user->getGrade() ?? '');
                $sheet->setCellValue('F' . $row, $user->getAverageGrade() ?? '');
                $sheet->setCellValue('G' . $row, $user->isActive() ? 'Sí' : 'No');
                $row++;
            }

            (new Xlsx($spreadsheet))->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment;filename="usuarios_' . date('Y-m-d') . '.xlsx"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    // ── US-305: Export all courses ───────────────────────────────────────────

    #[Route('/courses.xlsx', name: 'admin_export_courses')]
    #[IsGranted('ROLE_ADMIN')]
    public function exportCourses(EntityManagerInterface $em): StreamedResponse
    {
        $qb = $em->createQueryBuilder()
            ->select('c, t, cat')
            ->from(Course::class, 'c')
            ->leftJoin('c.teacher', 't')
            ->leftJoin('c.category', 'cat')
            ->orderBy('c.name', 'ASC');

        if ($this->tenantContext->hasSchool()) {
            $qb->andWhere('c.school = :_school')
               ->setParameter('_school', $this->tenantContext->getCurrentSchool());
        }

        $courses = $qb->getQuery()->getResult();

        $response = new StreamedResponse(function () use ($courses, $em) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Cursos');
            $sheet->setCellValue('A1', 'Nombre');
            $sheet->setCellValue('B1', 'Categoría');
            $sheet->setCellValue('C1', 'Profesor');
            $sheet->setCellValue('D1', 'Horario');
            $sheet->setCellValue('E1', 'Grados');
            $sheet->setCellValue('F1', 'Inscritos');
            $sheet->setCellValue('G1', 'Cupo máx.');
            $sheet->setCellValue('H1', 'Fecha límite');
            $sheet->setCellValue('I1', 'Activo');

            $row = 2;
            foreach ($courses as $course) {
                $enrolled = $em->getRepository(Enrollment::class)->count(['course' => $course]);
                $sheet->setCellValue('A' . $row, $course->getName());
                $sheet->setCellValue('B' . $row, $course->getCategory()?->getName() ?? '');
                $sheet->setCellValue('C' . $row, $course->getTeacher()?->getFullName() ?? '');
                $sheet->setCellValue('D' . $row, $course->getSchedule() ?? '');
                $sheet->setCellValue('E' . $row, implode(', ', $course->getTargetGrades() ?? []));
                $sheet->setCellValue('F' . $row, $enrolled);
                $sheet->setCellValue('G' . $row, $course->getMaxCapacity());
                $sheet->setCellValue('H' . $row, $course->getEnrollmentDeadline()?->format('d/m/Y') ?? '');
                $sheet->setCellValue('I' . $row, $course->isActive() ? 'Sí' : 'No');
                $row++;
            }

            (new Xlsx($spreadsheet))->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment;filename="cursos_' . date('Y-m-d') . '.xlsx"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }
}
