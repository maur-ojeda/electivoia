<?php

namespace App\Service;

use App\Repository\CourseRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class GeminiChatbotService
{
    private const GEMINI_API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent';
    
    public function __construct(
        private HttpClientInterface $httpClient,
        private CourseRepository $courseRepository,
        private LoggerInterface $logger,
        private string $geminiApiKey
    ) {
    }

    public function chat(string $userMessage, array $history = [], array $studentContext = []): array
    {
        try {
            // Obtener información de los cursos disponibles
            $coursesContext = $this->buildCoursesContext();

            // Construir el prompt con contexto
            $systemPrompt = $this->buildSystemPrompt($coursesContext, $studentContext);

            // Construir el array de contenidos incluyendo historial
            $contents = [['parts' => [['text' => $systemPrompt]]]];
            foreach ($history as $turn) {
                $contents[] = ['role' => 'user', 'parts' => [['text' => $turn['user']]]];
                $contents[] = ['role' => 'model', 'parts' => [['text' => $turn['bot']]]];
            }
            $contents[] = ['role' => 'user', 'parts' => [['text' => $userMessage]]];

            // Llamar a la API de Gemini
            $response = $this->httpClient->request('POST', self::GEMINI_API_URL, [
                'query' => ['key' => $this->geminiApiKey],
                'json' => [
                    'contents' => $contents,
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'topK' => 40,
                        'topP' => 0.95,
                        'maxOutputTokens' => 1024,
                    ]
                ]
            ]);

            $data = $response->toArray();
            
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                $botResponse = $data['candidates'][0]['content']['parts'][0]['text'];
                
                return [
                    'success' => true,
                    'message' => $botResponse
                ];
            }

            return [
                'success' => false,
                'message' => 'Lo siento, no pude procesar tu mensaje. ¿Podrías reformularlo?'
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error en Gemini chatbot: ' . $e->getMessage());
            
            // Detectar error de límite de cuota (429)
            if (str_contains($e->getMessage(), '429') || str_contains($e->getMessage(), 'RESOURCE_EXHAUSTED')) {
                return [
                    'success' => false,
                    'message' => 'El asistente IA está temporalmente ocupado. Por favor intenta de nuevo en unos segundos. 😊'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Lo siento, estoy teniendo problemas técnicos. Por favor intenta más tarde.'
            ];
        }
    }

    private function buildCoursesContext(): string
    {
        $courses = $this->courseRepository->findActiveForContext();
        $context = "CURSOS DISPONIBLES:\n\n";
        
        foreach ($courses as $course) {
            $context .= sprintf(
                "- %s (Categoría: %s)\n  Descripción: %s\n  Profesor: %s\n  Horario: %s\n  Cupos: %d/%d\n\n",
                $course->getName(),
                $course->getCategory()?->getName() ?? 'Sin categoría',
                $course->getDescription() ?? 'Sin descripción',
                $course->getTeacher()?->getFullName() ?? 'Por asignar',
                $course->getSchedule() ?? 'Por definir',
                $course->getCurrentEnrollment(),
                $course->getMaxCapacity()
            );
        }
        
        return $context;
    }

    private function buildSystemPrompt(string $coursesContext, array $studentContext = []): string
    {
        $studentBlock = $this->buildStudentContextBlock($studentContext);

        return <<<PROMPT
Eres ElectivoBot, el asistente virtual del sistema de cursos electivos de un colegio chileno.
{$studentBlock}
INFORMACIÓN DE CURSOS DISPONIBLES:
{$coursesContext}
INSTRUCCIONES:
- Sé amigable, cercano y motivador. Usa lenguaje apropiado para estudiantes de enseñanza media.
- Recomienda cursos basándote en los intereses del estudiante.
- Si te preguntan por un curso específico, busca en la lista y proporciona detalles.
- Si no sabes algo, sé honesto y sugiere contactar a un profesor o administrador.
- Mantén las respuestas concisas pero informativas (máximo 3-4 párrafos).
- Usa emojis ocasionalmente para ser más amigable 😊
- Si te preguntan por temas no relacionados con cursos, profesores, horarios o inscripciones, responde amablemente: "¡Ups! Solo puedo ayudarte con temas de cursos electivos, profesores, horarios e inscripciones. ¿Quieres que te ayude a encontrar un curso?" No intentes responder preguntas fuera de este alcance.

PROMPT;
    }

    private function buildStudentContextBlock(array $studentContext): string
    {
        if (empty($studentContext)) {
            return '';
        }

        $lines = [];
        $lines[] = 'CONTEXTO DEL ESTUDIANTE:';
        $lines[] = '- Nombre: ' . ($studentContext['name'] ?? 'Estudiante');
        $lines[] = '- Curso: ' . ($studentContext['grade'] ?? 'No especificado');

        // Interests: array like ['Filosofía' => 4, 'Artes' => 5]
        $interests = $studentContext['interests'] ?? [];
        if (!empty($interests)) {
            $interestParts = [];
            foreach ($interests as $topic => $score) {
                $interestParts[] = "{$topic} (nivel {$score})";
            }
            $lines[] = '- Intereses: ' . implode(', ', $interestParts);
        } else {
            $lines[] = '- Intereses: Sin intereses registrados aún';
        }

        // Enrolled courses
        $enrolledCourses = $studentContext['enrolledCourses'] ?? [];
        if (!empty($enrolledCourses)) {
            $courseNames = array_map(fn($c) => $c['name'] ?? $c, $enrolledCourses);
            $lines[] = '- Cursos inscrito actualmente: ' . implode(', ', $courseNames);
        } else {
            $lines[] = '- Cursos inscrito actualmente: Ninguno';
        }

        // Enrollment period status
        $enrollmentOpen = $studentContext['enrollmentOpen'] ?? null;
        if ($enrollmentOpen === true) {
            $lines[] = '- Período de inscripción: Abierto';
        } elseif ($enrollmentOpen === false) {
            $lines[] = '- Período de inscripción: Cerrado';
        }

        $lines[] = '';
        return implode("\n", $lines);
    }
}
