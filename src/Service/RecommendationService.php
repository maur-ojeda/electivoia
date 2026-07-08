<?php

namespace App\Service;

use App\Entity\Course;
use App\Entity\User;
use App\Repository\EnrollmentRepository;
use Doctrine\ORM\EntityManagerInterface;

class RecommendationService
{
    private const MAX_RESULTS = 6;
    private const INTEREST_MULTIPLIER = 10;
    private const AVAILABLE_CUPOS_BONUS = 5;
    private const FULL_COURSE_PENALTY = -10;

    public function __construct(
        private EntityManagerInterface $em,
        private EnrollmentRepository $enrollmentRepository,
    ) {}

    /**
     * Genera recomendaciones de cursos basadas en el perfil de intereses del estudiante.
     *
     * @return Course[]
     */
    public function getForStudent(User $student): array
    {
        return array_map(
            fn (array $item): Course => $item['course'],
            $this->getForStudentWithReasons($student),
        );
    }

    /**
     * Genera recomendaciones de cursos con puntuación y razones explicativas.
     *
     * Algoritmo de content-based filtering:
     * 1. Coincidencia de intereses: interest_level * 10 (señal principal)
     * 2. Bonus por cupos disponibles: +5 puntos
     * 3. Penalización por curso lleno: -10 puntos (no excluye)
     * 4. Exclusión si ya está inscrito
     * 5. Filtro por grado del estudiante
     *
     * @return array<int, array{course: Course, score: int, reasons: string[]}>
     */
    public function getForStudentWithReasons(User $student): array
    {
        $interests = $student->getInterestProfile()?->getInterests() ?? [];

        if (empty($interests)) {
            return [];
        }

        $studentGrade = $student->getGrade();

        if ($studentGrade === null) {
            return [];
        }

        $courses = $this->em->getRepository(Course::class)->findBy(['isActive' => true]);
        $results = [];

        foreach ($courses as $course) {
            $scored = $this->scoreCourse($course, $student, $interests, $studentGrade);

            if ($scored !== null) {
                $results[] = $scored;
            }
        }

        usort($results, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($results, 0, self::MAX_RESULTS);
    }

    /**
     * Evalúa un curso individual y devuelve su puntuación con razones, o null si debe excluirse.
     *
     * @param array<string, int> $interests
     *
     * @return array{course: Course, score: int, reasons: string[]}|null
     */
    private function scoreCourse(
        Course $course,
        User $student,
        array $interests,
        string $studentGrade,
    ): ?array {
        // Filtro: el curso debe incluir el grado del estudiante
        $targetGrades = $course->getTargetGrades();
        if ($targetGrades === null || !in_array($studentGrade, $targetGrades, true)) {
            return null;
        }

        // Excluir cursos en los que el estudiante ya está inscrito
        $alreadyEnrolled = $this->enrollmentRepository->findOneBy([
            'student' => $student,
            'course' => $course,
        ]) !== null;

        if ($alreadyEnrolled) {
            return null;
        }

        // La categoría debe existir y coincidir con un interés del estudiante
        $category = $course->getCategory();
        if ($category === null) {
            return null;
        }

        $categoryName = $category->getName();
        $interestLevel = $interests[$categoryName] ?? 0;

        if ($interestLevel <= 0) {
            return null;
        }

        // --- Cálculo de puntuación ---
        $score = $interestLevel * self::INTEREST_MULTIPLIER;
        $reasons = [];

        $reasons[] = sprintf(
            'Coincide con tu interés en %s (nivel %d)',
            $categoryName,
            $interestLevel,
        );

        $availableCupos = ($course->getMaxCapacity() ?? 0) - $course->getCurrentEnrollment();

        if ($availableCupos > 0) {
            $score += self::AVAILABLE_CUPOS_BONUS;
            $reasons[] = 'Tiene cupos disponibles';
        } else {
            $score += self::FULL_COURSE_PENALTY;
            // No se generan razones para penalizaciones
        }

        return [
            'course' => $course,
            'score' => $score,
            'reasons' => $reasons,
        ];
    }
}
