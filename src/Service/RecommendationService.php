<?php

namespace App\Service;

use App\Entity\Course;
use App\Entity\User;
use App\Repository\EnrollmentRepository;
use App\Repository\InterestProfileRepository;
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
        private InterestProfileRepository $interestProfileRepository,
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

    /**
     * Genera recomendaciones de cursos basadas en collaborative filtering.
     *
     * Algoritmo:
     * 1. Obtiene el perfil de intereses del estudiante.
     * 2. Encuentra otros estudiantes de la misma escuela con categorías
     *    compartidas en nivel >= 3 (peers).
     * 3. Calcula una puntuación de similitud: suma de min(mi_nivel, su_nivel)
     *    por cada categoría compartida.
     * 4. Recolecta los cursos en los que los peers están inscritos.
     * 5. Puntúa cada curso por la suma de similitudes de sus peers.
     * 6. Excluye cursos ya inscritos y cursos que no coincidan con el grado.
     *
     * @return array<int, array{course: Course, score: int, reasons: string[]}>
     */
    public function getCollaborativeRecommendations(User $student): array
    {
        $interests = $student->getInterestProfile()?->getInterests() ?? [];

        if (empty($interests)) {
            return [];
        }

        $studentGrade = $student->getGrade();

        if ($studentGrade === null) {
            return [];
        }

        $school = $student->getSchool();

        if ($school === null || $school->getId() === null) {
            return [];
        }

        $studentId = $student->getId();

        if ($studentId === null) {
            return [];
        }

        // 1. Encontrar perfiles de otros estudiantes en la misma escuela
        $otherProfiles = $this->interestProfileRepository->findOtherProfilesInSchool(
            $school->getId(),
            $studentId,
        );

        // 2. Calcular similitud con cada perfil
        $peers = [];

        /** @var \App\Entity\InterestProfile $profile */
        foreach ($otherProfiles as $profile) {
            $similarity = $this->calculateSimilarity($interests, $profile->getInterests());

            if ($similarity > 0) {
                $peerStudent = $profile->getStudent();

                if ($peerStudent !== null && $peerStudent->getId() !== null) {
                    $peers[$peerStudent->getId()] = [
                        'student' => $peerStudent,
                        'similarity' => $similarity,
                    ];
                }
            }
        }

        if (empty($peers)) {
            return [];
        }

        // 3. Obtener todas las inscripciones de los peers
        $peerStudents = array_column($peers, 'student');
        $enrollments = $this->enrollmentRepository->findEnrollmentsByStudents($peerStudents);

        // 4. Acumular puntuaciones por curso
        $courseData = []; // [courseId => ['course' => Course, 'score' => int, 'peerCount' => int]]

        foreach ($enrollments as $enrollment) {
            $course = $enrollment->getCourse();
            $enrolledStudent = $enrollment->getStudent();

            if ($course === null || $enrolledStudent === null) {
                continue;
            }

            $peerId = $enrolledStudent->getId();

            if (!isset($peers[$peerId])) {
                continue;
            }

            // Excluir cursos que no coincidan con el grado del estudiante
            $targetGrades = $course->getTargetGrades();

            if ($targetGrades === null || !in_array($studentGrade, $targetGrades, true)) {
                continue;
            }

            $courseId = $course->getId();

            if (!isset($courseData[$courseId])) {
                $courseData[$courseId] = [
                    'course' => $course,
                    'score' => 0,
                    'peerCount' => 0,
                ];
            }

            $courseData[$courseId]['score'] += $peers[$peerId]['similarity'];
            ++$courseData[$courseId]['peerCount'];
        }

        // 5. Construir resultados finales
        $results = [];

        foreach ($courseData as $data) {
            $course = $data['course'];

            // Excluir cursos en los que el estudiante ya está inscrito
            $alreadyEnrolled = $this->enrollmentRepository->findOneBy([
                'student' => $student,
                'course' => $course,
            ]) !== null;

            if ($alreadyEnrolled) {
                continue;
            }

            $results[] = [
                'course' => $course,
                'score' => $data['score'],
                'reasons' => [
                    'Estudiantes con intereses similares se inscribieron en este curso',
                    sprintf('%d compañeros con afinidad eligieron este curso', $data['peerCount']),
                ],
            ];
        }

        usort($results, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($results, 0, self::MAX_RESULTS);
    }

    /**
     * Genera recomendaciones híbridas combinando content-based y collaborative filtering.
     *
     * 1. Obtiene recomendaciones content-based (getForStudentWithReasons).
     * 2. Obtiene recomendaciones collaborative (getCollaborativeRecommendations).
     * 3. Fusiona: si un curso aparece en ambas, suma puntajes y combina razones.
     *    Si solo está en una, usa ese puntaje.
     * 4. Ordena por puntaje combinado descendente, limita a 6.
     *
     * @return array<int, array{course: Course, score: int, reasons: string[]}>
     */
    public function getHybridRecommendations(User $student): array
    {
        $contentBased = $this->getForStudentWithReasons($student);
        $collaborative = $this->getCollaborativeRecommendations($student);

        $merged = [];

        foreach ($contentBased as $item) {
            $courseId = $item['course']->getId();
            $merged[$courseId] = [
                'course' => $item['course'],
                'score' => $item['score'],
                'reasons' => $item['reasons'],
            ];
        }

        foreach ($collaborative as $item) {
            $courseId = $item['course']->getId();

            if (isset($merged[$courseId])) {
                $merged[$courseId]['score'] += $item['score'];
                $merged[$courseId]['reasons'] = array_values(
                    array_unique(array_merge($merged[$courseId]['reasons'], $item['reasons'])),
                );
            } else {
                $merged[$courseId] = [
                    'course' => $item['course'],
                    'score' => $item['score'],
                    'reasons' => $item['reasons'],
                ];
            }
        }

        $results = array_values($merged);

        usort($results, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($results, 0, self::MAX_RESULTS);
    }

    /**
     * Calcula la similitud entre dos perfiles de intereses.
     *
     * Para cada categoría compartida donde el nivel del peer es >= 3,
     * suma min(mi_nivel, su_nivel) a la similitud total.
     *
     * @param array<string, int> $myInterests
     * @param array<string, int> $theirInterests
     */
    private function calculateSimilarity(array $myInterests, array $theirInterests): int
    {
        $similarity = 0;

        foreach ($myInterests as $category => $myLevel) {
            $theirLevel = $theirInterests[$category] ?? null;

            if ($theirLevel !== null && $theirLevel >= 3) {
                $similarity += min($myLevel, $theirLevel);
            }
        }

        return $similarity;
    }
}
