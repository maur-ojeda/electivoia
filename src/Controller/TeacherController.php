<?php

namespace App\Controller;

use App\Service\ExportService;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Course;
use App\Entity\CourseCategory;
use App\Entity\Enrollment;
use App\Entity\Attendance;
use App\Entity\User;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\ExpressionLanguage\Expression;


#[Route('/teacher', name: 'teacher_')]
#[IsGranted(new Expression('is_granted("ROLE_TEACHER") or is_granted("ROLE_ADMIN")'))]
class TeacherController extends AbstractController
{
    public function __construct(
        private TenantContext $tenantContext,
        private ExportService $exportService,
    ) {}

    #[Route('/courses', name: 'courses')]
    public function courses(Request $request, EntityManagerInterface $em): Response
    {
        $teacher = $this->getUser();

        $searchQuery = trim((string) $request->query->get('q', ''));
        $selectedCategory = $request->query->get('category');

        // Subconsulta para contar inscripciones (evita N+1 al contar)
        $countQb = $em->createQueryBuilder()
            ->select('c.id, COUNT(e.id) as enrollmentCount')
            ->from(\App\Entity\Course::class, 'c')
            ->leftJoin('c.enrollments', 'e')
            ->where('c.teacher = :teacher')
            ->andWhere('c.isActive = true')
            ->setParameter('teacher', $teacher);

        if ($this->tenantContext->hasSchool()) {
            $countQb->andWhere('c.school = :_school')
                ->setParameter('_school', $this->tenantContext->getCurrentSchool());
        }

        // Aplicar mismos filtros a la subconsulta
        if ($selectedCategory) {
            $countQb->andWhere('c.category = :categoryId')
                ->setParameter('categoryId', $selectedCategory);
        }
        if ($searchQuery !== '') {
            $countQb->andWhere('LOWER(c.name) LIKE :search OR LOWER(c.description) LIKE :search')
                ->setParameter('search', '%' . strtolower($searchQuery) . '%');
        }

        $countResults = $countQb->groupBy('c.id')->getQuery()->getResult();
        $enrollmentCounts = [];
        foreach ($countResults as $row) {
            $enrollmentCounts[$row['id']] = (int) $row['enrollmentCount'];
        }

        // Consulta principal: cursos + relaciones (optimizada con fetch join)
        $qb = $em->createQueryBuilder()
            ->select('c, cat, t, e, s')
            ->from(\App\Entity\Course::class, 'c')
            ->leftJoin('c.category', 'cat')
            ->leftJoin('c.teacher', 't')
            ->leftJoin('c.enrollments', 'e')
            ->leftJoin('e.student', 's')
            ->where('c.teacher = :teacher')
            ->andWhere('c.isActive = true')
            ->setParameter('teacher', $teacher);

        if ($this->tenantContext->hasSchool()) {
            $qb->andWhere('c.school = :_school')
                ->setParameter('_school', $this->tenantContext->getCurrentSchool());
        }

        if ($selectedCategory) {
            $qb->andWhere('c.category = :categoryId')
                ->setParameter('categoryId', $selectedCategory);
        }
        if ($searchQuery !== '') {
            $qb->andWhere('LOWER(c.name) LIKE :search OR LOWER(c.description) LIKE :search')
                ->setParameter('search', '%' . strtolower($searchQuery) . '%');
        }

        $courses = $qb->getQuery()->getResult();

        // Reagrupar enrollments por curso (como antes)
        $enrollmentsByCourse = [];
        foreach ($courses as $course) {
            // Doctrine ya trajo los enrollments gracias al fetch join
            $enrollmentsByCourse[$course->getId()] = $course->getEnrollments()->toArray();
        }

        // Obtener categorías para el filtro
        $allCategories = $em->getRepository(\App\Entity\CourseCategory::class)->findAll();


        $enrollmentStatus = $request->query->get('enrollmentStatus');






        return $this->render('teacher/courses.html.twig', [
            'courses' => $courses,
            'enrollmentsByCourse' => $enrollmentsByCourse,
            'enrollmentCounts' => $enrollmentCounts, // útil para mostrar cupo sin iterar
            'allCategories' => $allCategories,
            'selectedCategory' => $selectedCategory,
            'searchQuery' => $searchQuery,
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

    #[Route('/report/{id}', name: 'report')]
    public function report(Course $course, EntityManagerInterface $em): Response
    {
        $teacher = $this->getUser();
        if ($course->getTeacher() !== $teacher) {
            throw $this->createAccessDeniedException('No tienes permiso para ver este reporte.');
        }

        $enrollments = $em->getRepository(Enrollment::class)->findBy(['course' => $course]);

        $allAttendances = $em->getRepository(Attendance::class)->findBy(
            ['course' => $course],
            ['date' => 'ASC']
        );

        $dates = [];
        foreach ($allAttendances as $att) {
            $dateStr = $att->getDate()->format('Y-m-d');
            if (!in_array($dateStr, $dates)) {
                $dates[] = $dateStr;
            }
        }

        $reportData = [];
        foreach ($enrollments as $enrollment) {
            $student = $enrollment->getStudent();
            $records = $em->getRepository(Attendance::class)->findBy(
                ['student' => $student, 'course' => $course],
                ['date' => 'ASC']
            );

            $total = count($records);
            $present = count(array_filter($records, fn($a) => $a->getStatus() === 'present'));
            $absent = count(array_filter($records, fn($a) => $a->getStatus() === 'absent'));
            $justified = count(array_filter($records, fn($a) => $a->getStatus() === 'justified'));
            $percentage = $total > 0 ? round(($present + $justified) / $total * 100) : null;

            $reportData[] = [
                'student' => $student,
                'total' => $total,
                'present' => $present,
                'absent' => $absent,
                'justified' => $justified,
                'percentage' => $percentage,
            ];
        }

        usort($reportData, fn($a, $b) => ($a['percentage'] ?? -1) <=> ($b['percentage'] ?? -1));

        return $this->render('teacher/report.html.twig', [
            'course' => $course,
            'reportData' => $reportData,
            'totalSessions' => count($dates),
        ]);
    }

    #[Route('/report/{id}/export', name: 'report_export')]
    public function exportReport(Course $course, EntityManagerInterface $em): StreamedResponse
    {
        $teacher = $this->getUser();
        if ($course->getTeacher() !== $teacher) {
            throw $this->createAccessDeniedException('No tienes permiso para exportar este reporte.');
        }

        $enrollments = $em->getRepository(Enrollment::class)->findBy(['course' => $course]);

        $response = new StreamedResponse();
        $response->setCallback(function () use ($course, $enrollments, $em) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setCellValue('A1', 'RUT');
            $sheet->setCellValue('B1', 'Nombre');
            $sheet->setCellValue('C1', 'Grado');
            $sheet->setCellValue('D1', 'Promedio');
            $sheet->setCellValue('E1', 'Sesiones totales');
            $sheet->setCellValue('F1', 'Presentes');
            $sheet->setCellValue('G1', 'Ausentes');
            $sheet->setCellValue('H1', 'Justificados');
            $sheet->setCellValue('I1', '% Asistencia');

            $row = 2;
            foreach ($enrollments as $enrollment) {
                $student = $enrollment->getStudent();
                $records = $em->getRepository(Attendance::class)->findBy(['student' => $student, 'course' => $course]);
                $total = count($records);
                $present = count(array_filter($records, fn($a) => $a->getStatus() === 'present'));
                $absent = count(array_filter($records, fn($a) => $a->getStatus() === 'absent'));
                $justified = count(array_filter($records, fn($a) => $a->getStatus() === 'justified'));
                $percentage = $total > 0 ? round(($present + $justified) / $total * 100) : 0;

                $sheet->setCellValue('A' . $row, $student->getRut());
                $sheet->setCellValue('B' . $row, $student->getFullName());
                $sheet->setCellValue('C' . $row, $student->getGrade());
                $sheet->setCellValue('D' . $row, $student->getAverageGrade());
                $sheet->setCellValue('E' . $row, $total);
                $sheet->setCellValue('F' . $row, $present);
                $sheet->setCellValue('G' . $row, $absent);
                $sheet->setCellValue('H' . $row, $justified);
                $sheet->setCellValue('I' . $row, $percentage . '%');
                $row++;
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $filename = 'reporte_asistencia_' . $course->getName() . '_' . date('Y-m-d') . '.xlsx';
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment;filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    // ── HDU-S2-01: Crear curso ────────────────────────────────────────────────

    #[Route('/courses/new', name: 'course_new')]
    public function courseNew(Request $request, EntityManagerInterface $em): Response
    {
        $categories = $em->getRepository(CourseCategory::class)->findAll();
        $errors = [];
        $data = [];

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $name = trim($data['name'] ?? '');
            $maxCapacity = (int) ($data['maxCapacity'] ?? 0);
            $targetGrades = $data['targetGrades'] ?? [];
            $description = trim($data['description'] ?? '');
            $deadlineRaw = trim($data['enrollmentDeadline'] ?? '');
            $categoryId = $data['category'] ?? null;
            $schedule = trim($data['schedule'] ?? '');

            if ($name === '') {
                $errors[] = 'El nombre del curso es obligatorio.';
            }
            if ($maxCapacity < 1) {
                $errors[] = 'La capacidad máxima debe ser al menos 1.';
            }
            if (empty($targetGrades)) {
                $errors[] = 'Debes seleccionar al menos un grado objetivo.';
            }

            if (empty($errors)) {
                $course = new Course();
                $course->setName($name);
                $course->setDescription($description ?: null);
                $course->setMaxCapacity($maxCapacity);
                $course->setTargetGrades($targetGrades);
                $course->setTeacher($this->getUser());
                $course->setIsActive(true);
                $course->setSchedule($schedule ?: null);

                if ($this->tenantContext->hasSchool()) {
                    $course->setSchool($this->tenantContext->getCurrentSchool());
                }

                if ($deadlineRaw !== '') {
                    $course->setEnrollmentDeadline(new \DateTimeImmutable($deadlineRaw));
                }
                if ($categoryId) {
                    $category = $em->getRepository(CourseCategory::class)->find($categoryId);
                    $course->setCategory($category);
                }

                $em->persist($course);
                $em->flush();
                $this->addFlash('success', 'Curso creado correctamente.');
                return $this->redirectToRoute('teacher_courses');
            }
        }

        return $this->render('teacher/course_form.html.twig', [
            'course' => null,
            'categories' => $categories,
            'errors' => $errors,
            'data' => $data,
            'formTitle' => 'Crear nuevo curso',
            'submitLabel' => 'Crear curso',
        ]);
    }

    // ── HDU-S2-02: Editar curso ───────────────────────────────────────────────

    #[Route('/courses/{id}/edit', name: 'course_edit')]
    public function courseEdit(Course $course, Request $request, EntityManagerInterface $em): Response
    {
        $teacher = $this->getUser();
        if ($course->getTeacher() !== $teacher) {
            throw $this->createAccessDeniedException('No tienes permiso para editar este curso.');
        }

        $categories = $em->getRepository(CourseCategory::class)->findAll();
        $errors = [];
        $data = [];

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $name = trim($data['name'] ?? '');
            $maxCapacity = (int) ($data['maxCapacity'] ?? 0);
            $targetGrades = $data['targetGrades'] ?? [];
            $description = trim($data['description'] ?? '');
            $deadlineRaw = trim($data['enrollmentDeadline'] ?? '');
            $categoryId = $data['category'] ?? null;
            $schedule = trim($data['schedule'] ?? '');

            if ($name === '') {
                $errors[] = 'El nombre del curso es obligatorio.';
            }
            if ($maxCapacity < 1) {
                $errors[] = 'La capacidad máxima debe ser al menos 1.';
            }
            if ($maxCapacity < $course->getCurrentEnrollment()) {
                $errors[] = 'No puedes reducir la capacidad por debajo de los alumnos ya inscritos (' . $course->getCurrentEnrollment() . ').';
            }
            if (empty($targetGrades)) {
                $errors[] = 'Debes seleccionar al menos un grado objetivo.';
            }

            if (empty($errors)) {
                $course->setName($name);
                $course->setDescription($description ?: null);
                $course->setMaxCapacity($maxCapacity);
                $course->setTargetGrades($targetGrades);
                $course->setEnrollmentDeadline($deadlineRaw !== '' ? new \DateTimeImmutable($deadlineRaw) : null);
                $course->setSchedule($schedule ?: null);

                $category = $categoryId ? $em->getRepository(CourseCategory::class)->find($categoryId) : null;
                $course->setCategory($category);

                $em->flush();
                $this->addFlash('success', 'Curso actualizado correctamente.');
                return $this->redirectToRoute('teacher_courses');
            }
        } else {
            $data = [
                'name' => $course->getName(),
                'description' => $course->getDescription(),
                'maxCapacity' => $course->getMaxCapacity(),
                'targetGrades' => $course->getTargetGrades() ?? [],
                'enrollmentDeadline' => $course->getEnrollmentDeadline()?->format('Y-m-d\TH:i'),
                'category' => $course->getCategory()?->getId(),
                'schedule' => $course->getSchedule(),
            ];
        }

        return $this->render('teacher/course_form.html.twig', [
            'course' => $course,
            'categories' => $categories,
            'errors' => $errors,
            'data' => $data,
            'formTitle' => 'Editar curso',
            'submitLabel' => 'Guardar cambios',
        ]);
    }

    // ── HDU-S2-03: Activar / desactivar curso ────────────────────────────────

    #[Route('/courses/{id}/toggle-active', name: 'course_toggle_active', methods: ['POST'])]
    public function courseToggleActive(Course $course, EntityManagerInterface $em): Response
    {
        if ($course->getTeacher() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $course->setIsActive(!$course->isActive());
        $em->flush();

        $msg = $course->isActive() ? 'Curso activado — ya aparece en el catálogo.' : 'Curso desactivado — ya no aparece en el catálogo.';
        $this->addFlash('success', $msg);
        return $this->redirectToRoute('teacher_courses');
    }

    // ── HDU-S2-04: Lista de inscritos ─────────────────────────────────────────

    #[Route('/courses/{id}/students', name: 'course_students')]
    public function courseStudents(Course $course, EntityManagerInterface $em): Response
    {
        if ($course->getTeacher() !== $this->getUser()) {
            throw $this->createAccessDeniedException('No tienes permiso para ver este listado.');
        }

        $enrollments = $em->getRepository(Enrollment::class)->findBy(
            ['course' => $course],
            ['enrolledAt' => 'ASC']
        );

        return $this->render('teacher/course_students.html.twig', [
            'course' => $course,
            'enrollments' => $enrollments,
        ]);
    }

    #[Route('/export/course/{id}/students.xlsx', name: 'export_students')]
    public function exportStudents(Course $course): StreamedResponse
    {
        if ($course->getTeacher() !== $this->getUser()) {
            throw $this->createAccessDeniedException('No tienes permiso para exportar este curso.');
        }

        $data = $this->exportService->getStudentExportData($course);
        $filename = 'alumnos_' . $course->getName() . '.xlsx';

        return $this->exportService->generateStudentExcel($data, $filename);
    }
}
