<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\Request; // Importar Request
use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\Attendance;
use App\Entity\User; // Importar User
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;



#[Route('/teacher', name: 'teacher_')]
#[IsGranted('ROLE_TEACHER')]
class TeacherController extends AbstractController
{
    #[Route('/courses', name: 'courses')]
    public function courses(EntityManagerInterface $em): Response
    {
        $teacher = $this->getUser();
        $courses = $em->getRepository(\App\Entity\Course::class)->findBy(['teacher' => $teacher]);

        $enrollmentsByCourse = [];
        foreach ($courses as $course) {
            $enrollments = $em->getRepository(\App\Entity\Enrollment::class)->findBy(['course' => $course]);
            $enrollmentsByCourse[$course->getId()] = $enrollments;
        }

        return $this->render('teacher/courses.html.twig', [
            'courses' => $courses,
            'enrollmentsByCourse' => $enrollmentsByCourse,
        ]);
    }

    #[Route('/attendance/{id}', name: 'attendance')]
    public function attendance(Course $course, EntityManagerInterface $em): Response
    {
        //$this->denyAccessUnlessGranted('view', $course); // Opcional: voter
        $teacher = $this->getUser();
        if ($course->getTeacher() !== $teacher) {
            throw $this->createAccessDeniedException('No tienes permiso para registrar asistencia en este curso.');
        }
        // Obtener alumnos inscritos
        $enrollments = $em->getRepository(Enrollment::class)->findBy(['course' => $course]);
        $students = array_map(fn($e) => $e->getStudent(), $enrollments);

        // Fecha actual (o permitir seleccionar)
        $date = new \DateTime();

        // Cargar asistencias existentes para hoy
        $existing = $em->getRepository(Attendance::class)->findBy([
            'course' => $course,
            'date' => $date,
        ]);
        $attendanceMap = [];
        foreach ($existing as $att) {
            $attendanceMap[$att->getStudent()->getId()] = $att->getStatus();
        }

        return $this->render('teacher/attendance.html.twig', [
            'course' => $course,
            'students' => $students,
            'date' => $date,
            'attendanceMap' => $attendanceMap,
        ]);
    }

    #[Route('/attendance/{id}/save', name: 'attendance_save', methods: ['POST'])]
    public function saveAttendance(Course $course, Request $request, EntityManagerInterface $em): Response
    {
        $date = new \DateTime();

        // CORRECCIÓN: Se obtienen todos los datos del POST y se accede a las claves con el 
        // operador ?? [] para evitar el error de tipo en el segundo argumento de InputBag::get().
        $postData = $request->request->all();
        $studentIds = $postData['student_ids'] ?? [];
        $statuses = $postData['status'] ?? [];

        // Eliminar asistencias anteriores para hoy
        $em->getRepository(Attendance::class)
            ->createQueryBuilder('a')
            ->delete()
            ->where('a.course = :course AND a.date = :date')
            ->setParameter('course', $course)
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();

        // Guardar nuevas asistencias
        foreach ($studentIds as $studentId) {
            $student = $em->getRepository(User::class)->find($studentId);
            if ($student) {
                $attendance = new Attendance();
                $attendance->setStudent($student);
                $attendance->setCourse($course);
                $attendance->setDate($date);
                // Asegurar que el estado es válido o usar 'present' como default
                $attendance->setStatus($statuses[$studentId] ?? 'present');
                $em->persist($attendance);
            }
        }

        $em->flush();
        $this->addFlash('success', 'Asistencia registrada correctamente.');
        return $this->redirectToRoute('teacher_attendance', ['id' => $course->getId()]);
    }

    #[Route('/export/course/{id}/students.xlsx', name: 'export_students')]
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
