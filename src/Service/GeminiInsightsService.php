<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Calls Gemini to generate natural-language insights from course statistics.
 * HU-33: "Como sistema de reportes, quiero transformar datos brutos en resúmenes en lenguaje natural."
 */
class GeminiInsightsService
{
    private const GEMINI_API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $geminiApiKey
    ) {}

    /**
     * @param array $stats Keys: total_courses, total_enrolled, total_capacity, avg_occupancy, by_category
     * @return array<string> List of insight sentences, or fallback hardcoded insights on error
     */
    public function generateInsights(array $stats): array
    {
        $prompt = $this->buildPrompt($stats);

        try {
            $response = $this->httpClient->request('POST', self::GEMINI_API_URL, [
                'query' => ['key' => $this->geminiApiKey],
                'json'  => [
                    'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                    'generationConfig' => [
                        'temperature'     => 0.4,
                        'maxOutputTokens' => 512,
                    ],
                ],
            ]);

            $data = $response->toArray();
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

            if (empty($text)) {
                return $this->fallbackInsights($stats);
            }

            // Split into individual bullets
            $lines = array_filter(
                array_map('trim', explode("\n", $text)),
                fn($l) => $l !== ''
            );

            return array_values($lines);
        } catch (\Throwable $e) {
            $this->logger->warning('GeminiInsightsService error: ' . $e->getMessage());
            return $this->fallbackInsights($stats);
        }
    }

    private function buildPrompt(array $stats): string
    {
        $categorySummary = '';
        foreach ($stats['by_category'] as $cat => $count) {
            $categorySummary .= "- {$cat}: {$count} inscripciones\n";
        }

        return <<<PROMPT
Eres un analista educativo experto en colegios chilenos.
Analiza los siguientes datos de inscripción en cursos electivos y genera 5 insights breves en español (una oración cada uno, en forma de lista con guiones).
Los insights deben ser concretos, accionables y orientados a la toma de decisiones del equipo directivo.

DATOS:
- Cursos activos: {$stats['total_courses']}
- Total de inscripciones: {$stats['total_enrolled']}
- Capacidad total: {$stats['total_capacity']}
- Ocupación promedio: {$stats['avg_occupancy']}%
- Inscripciones por área:
{$categorySummary}

Genera exactamente 5 bullets, uno por línea, comenzando cada uno con "- ".
PROMPT;
    }

    private function fallbackInsights(array $stats): array
    {
        $topCat = array_key_first($stats['by_category'] ?? []);

        return [
            "- Se registran {$stats['total_enrolled']} inscripciones en {$stats['total_courses']} cursos activos.",
            "- La ocupación promedio del sistema es del {$stats['avg_occupancy']}%.",
            $topCat ? "- El área con mayor demanda es '{$topCat}' con {$stats['by_category'][$topCat]} inscripciones." : '- No hay datos de categoría disponibles.',
            '- Se recomienda revisar los cursos con baja inscripción para ajustar la oferta o reforzar su difusión.',
            '- Considere abrir cupos adicionales en las áreas con mayor demanda para el próximo período.',
        ];
    }
}
