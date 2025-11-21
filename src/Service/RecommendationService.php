<?php

namespace App\Service;

use App\Entity\Course;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class RecommendationService
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Genera recomendaciones de cursos basadas en el perfil de intereses del estudiante.
     */
    public function getForStudent(User $student): array
    {
        $interests = $student->getInterestProfile()?->getInterests() ?? [];
        if (empty($interests)) {
            return [];
        }

        $courses = $this->em->getRepository(Course::class)->findBy(['isActive' => true]);
        $recommended = [];

        foreach ($courses as $course) {
            $score = 0;
            $description = strtolower($course->getDescription() ?? '');

            // Asigna puntos según coincidencia de intereses
            if (($interests['arte'] ?? 0) >= 4 && str_contains($description, 'arte')) {
                $score += ($interests['arte'] ?? 0);
            }
            if (($interests['ciencia'] ?? 0) >= 4 && (str_contains($description, 'ciencia') || str_contains($description, 'científ'))) {
                $score += ($interests['ciencia'] ?? 0);
            }
            if (($interests['tecnologia'] ?? 0) >= 4 && (str_contains($description, 'tecnología') || str_contains($description, 'tecnolog') || str_contains($description, 'programación'))) {
                $score += ($interests['tecnologia'] ?? 0);
            }
            if (($interests['deporte'] ?? 0) >= 4 && str_contains($description, 'deporte')) {
                $score += ($interests['deporte'] ?? 0);
            }
            if (($interests['musica'] ?? 0) >= 4 && str_contains($description, 'música')) {
                $score += ($interests['musica'] ?? 0);
            }

            if ($score > 0) {
                $recommended[] = $course;
            }
        }

        // Ordena por puntuación (opcional) y limita a 5
        usort($recommended, fn($a, $b) => rand(-1, 1)); // mezcla aleatoria para simplicidad
        return array_slice($recommended, 0, 5);
    }

    public function getForStudentWithReasons(User $student): array
    {
        $interests = $student->getInterestProfile()?->getInterests() ?? [];
        $courses = $this->em->getRepository(Course::class)->findBy(['isActive' => true]);
        $results = [];

        foreach ($courses as $course) {
            $reasons = [];
            $cat = $course->getCategory();

            if (!$cat) continue;

            $catName = $cat->getName();
            if (($interests[$catName] ?? 0) >= 4) {
                $reasons[] = $catName;
            }

            if (!empty($reasons)) {
                $results[] = ['course' => $course, 'reasons' => $reasons];
            }
        }

        return array_slice($results, 0, 5);
    }
}
