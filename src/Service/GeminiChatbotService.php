<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\CourseRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Psr\Log\LoggerInterface;

class GeminiChatbotService
{
    private const GEMINI_API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent';
    private const MAX_TOOL_ITERATIONS = 3;

    /** @var array<int, array{name: string, description: string, parameters: array}> */
    private const TOOL_DECLARATIONS = [
        [
            'name' => 'get_available_courses',
            'description' => 'Lista los cursos electivos disponibles. Usa esta función cuando el estudiante quiera ver cursos, buscar por categoría, o ver opciones.',
            'parameters' => [
                'type' => 'OBJECT',
                'properties' => [
                    'category' => [
                        'type' => 'STRING',
                        'description' => "Filtrar por área (ej: 'Filosofía', 'Artes', 'Ciencias'). Opcional.",
                    ],
                    'search' => [
                        'type' => 'STRING',
                        'description' => 'Buscar por nombre o descripción. Opcional.',
                    ],
                ],
            ],
        ],
        [
            'name' => 'get_course_details',
            'description' => 'Muestra detalles completos de un curso específico: descripción, profesor, horario, cupos, y si el estudiante ya está inscrito.',
            'parameters' => [
                'type' => 'OBJECT',
                'properties' => [
                    'course_id' => [
                        'type' => 'INTEGER',
                        'description' => 'ID numérico del curso',
                    ],
                ],
                'required' => ['course_id'],
            ],
        ],
        [
            'name' => 'enroll_in_course',
            'description' => "Muestra los detalles de inscripción de un curso y verifica si el estudiante puede inscribirse. NO inscribe directamente — solo muestra información para que el estudiante confirme.",
            'parameters' => [
                'type' => 'OBJECT',
                'properties' => [
                    'course_id' => [
                        'type' => 'INTEGER',
                        'description' => 'ID numérico del curso a inscribir',
                    ],
                ],
                'required' => ['course_id'],
            ],
        ],
        [
            'name' => 'confirm_enrollment',
            'description' => "Confirma la inscripción del estudiante en un curso DESPUÉS de que el estudiante dio confirmación explícita (dijo 'sí', 'quiero', 'dale', etc.).",
            'parameters' => [
                'type' => 'OBJECT',
                'properties' => [
                    'course_id' => [
                        'type' => 'INTEGER',
                        'description' => 'ID numérico del curso a confirmar inscripción',
                    ],
                ],
                'required' => ['course_id'],
            ],
        ],
    ];

    private ?User $currentUser = null;

    public function __construct(
        private HttpClientInterface $httpClient,
        private CourseRepository $courseRepository,
        private EnrollmentService $enrollmentService,
        private LoggerInterface $logger,
        private string $geminiApiKey
    ) {
    }

    public function chat(string $userMessage, array $history = [], array $studentContext = [], ?User $user = null): array
    {
        try {
            $this->currentUser = $user;

            $coursesContext = $this->buildCoursesContext();
            $systemPrompt = $this->buildSystemPrompt($coursesContext, $studentContext);

            $contents = [['parts' => [['text' => $systemPrompt]]]];
            foreach ($history as $turn) {
                $contents[] = ['role' => 'user', 'parts' => [['text' => $turn['user']]]];
                $contents[] = ['role' => 'model', 'parts' => [['text' => $turn['bot']]]];
            }
            $contents[] = ['role' => 'user', 'parts' => [['text' => $userMessage]]];

            $lastTextResponse = null;

            for ($iteration = 0; $iteration < self::MAX_TOOL_ITERATIONS; $iteration++) {
                $response = $this->callGeminiApi($contents);
                $data = $response->toArray();

                $parts = $data['candidates'][0]['content']['parts'] ?? [];

                // Extract text response if present
                foreach ($parts as $part) {
                    if (isset($part['text'])) {
                        $lastTextResponse = $part['text'];
                    }
                }

                // Check for function call
                $functionCall = null;
                foreach ($parts as $part) {
                    if (isset($part['functionCall'])) {
                        $functionCall = $part['functionCall'];
                        break;
                    }
                }

                if ($functionCall === null) {
                    // No function call — return text response
                    if ($lastTextResponse !== null) {
                        return ['success' => true, 'message' => $lastTextResponse];
                    }

                    return [
                        'success' => false,
                        'message' => 'Lo siento, no pude procesar tu mensaje. ¿Podrías reformularlo?',
                    ];
                }

                // Execute the tool
                $toolName = $functionCall['name'];
                $toolArgs = $functionCall['args'] ?? [];
                $toolResult = $this->executeTool($toolName, $toolArgs);

                // Append function call + response to contents
                $contents[] = [
                    'role' => 'model',
                    'parts' => [['functionCall' => $functionCall]],
                ];
                $contents[] = [
                    'role' => 'user',
                    'parts' => [[
                        'functionResponse' => [
                            'name' => $toolName,
                            'response' => $toolResult,
                        ],
                    ]],
                ];
            }

            // Loop cap reached
            $this->logger->warning(
                'Gemini chatbot tool_call loop reached max iterations (' . self::MAX_TOOL_ITERATIONS . ')'
            );

            return [
                'success' => true,
                'message' => $lastTextResponse ?? 'Lo siento, no pude completar tu solicitud.',
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error en Gemini chatbot: ' . $e->getMessage());

            if (str_contains($e->getMessage(), '429') || str_contains($e->getMessage(), 'RESOURCE_EXHAUSTED')) {
                return [
                    'success' => false,
                    'message' => 'El asistente IA está temporalmente ocupado. Por favor intenta de nuevo en unos segundos. 😊',
                ];
            }

            return [
                'success' => false,
                'message' => 'Lo siento, estoy teniendo problemas técnicos. Por favor intenta más tarde.',
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

    private function callGeminiApi(array $contents): ResponseInterface
    {
        return $this->httpClient->request('POST', self::GEMINI_API_URL, [
            'query' => ['key' => $this->geminiApiKey],
            'json' => [
                'contents' => $contents,
                'tools' => self::TOOL_DECLARATIONS,
                'toolConfig' => [
                    'functionCallingConfig' => [
                        'mode' => 'AUTO',
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                    'topK' => 40,
                    'topP' => 0.95,
                    'maxOutputTokens' => 1024,
                ],
            ],
        ]);
    }

    /**
     * Execute a tool call and return the result as an array.
     */
    private function executeTool(string $toolName, array $args): array
    {
        try {
            switch ($toolName) {
                case 'get_available_courses':
                    return $this->enrollmentService->getAvailableCourses(
                        category: $args['category'] ?? null,
                        search: $args['search'] ?? null,
                    );

                case 'get_course_details':
                    $courseId = $args['course_id'] ?? 0;
                    if ($this->currentUser === null) {
                        return ['error' => 'Se requiere autenticación para ver detalles del curso.'];
                    }
                    return $this->enrollmentService->getCourseDetails(
                        courseId: (int) $courseId,
                        student: $this->currentUser,
                    );

                case 'enroll_in_course':
                    $courseId = $args['course_id'] ?? 0;
                    if ($this->currentUser === null) {
                        return ['error' => 'Se requiere autenticación para inscribirse.'];
                    }
                    // Preview only — get course details, do NOT enroll
                    $details = $this->enrollmentService->getCourseDetails(
                        courseId: (int) $courseId,
                        student: $this->currentUser,
                    );

                    if ($details['id'] === 0) {
                        return [
                            'course_id' => (int) $courseId,
                            'course_name' => '',
                            'spots_available' => 0,
                            'enrolled' => false,
                            'can_enroll' => false,
                            'message' => 'Curso no encontrado.',
                        ];
                    }

                    return [
                        'course_id' => $details['id'],
                        'course_name' => $details['name'],
                        'spots_available' => $details['spots_available'],
                        'enrolled' => $details['enrolled'],
                        'can_enroll' => $details['can_enroll'],
                        'message' => $details['enrollment_message']
                            ?: "Hay {$details['spots_available']} cupos disponibles. ¿Te inscribís?",
                    ];

                case 'confirm_enrollment':
                    $courseId = $args['course_id'] ?? 0;
                    if ($this->currentUser === null) {
                        return ['success' => false, 'message' => 'Se requiere autenticación para inscribirse.'];
                    }

                    $course = $this->courseRepository->find((int) $courseId);
                    if ($course === null) {
                        return ['success' => false, 'message' => 'Curso no encontrado.'];
                    }

                    $result = $this->enrollmentService->enrollStudent($this->currentUser, $course);

                    if ($result['success']) {
                        return [
                            'success' => true,
                            'message' => "¡Te inscribiste exitosamente en {$course->getName()}!",
                        ];
                    }

                    return [
                        'success' => false,
                        'message' => $result['message'],
                    ];

                default:
                    return ['error' => "Herramienta no reconocida: {$toolName}"];
            }
        } catch (\Exception $e) {
            $this->logger->error("Error ejecutando tool '{$toolName}': " . $e->getMessage());
            return ['error' => 'Error al procesar la solicitud. Por favor intenta de nuevo.'];
        }
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
- Cuando el estudiante quiera ver cursos, usa get_available_courses.
- Para detalles de un curso específico, usa get_course_details.
- Para inscribir a un estudiante:
  a. Primero usa enroll_in_course para mostrar detalles y cupos.
  b. Pregunta "¿Te inscribís?" al estudiante.
  c. SOLO cuando el estudiante confirme explícitamente ("sí", "quiero", "dale"), usa confirm_enrollment.
- NUNCA inscribas sin confirmación explícita del estudiante.
- Si el estudiante pregunta por temas no relacionados con cursos, profesores, horarios o inscripciones, responde amablemente: "¡Ups! Solo puedo ayudarte con temas de cursos electivos, profesores, horarios e inscripciones. ¿Quieres que te ayude a encontrar un curso?" No intentes responder preguntas fuera de este alcance.
- Si el estudiante pide desinscribirse o darse de baja de un curso, indícale amablemente que debe ir a su perfil para gestionar sus inscripciones. NUNCA intentes desinscribir al estudiante.
- Mantén las respuestas concisas pero informativas (máximo 3-4 párrafos).
- Usa emojis ocasionalmente para ser más amigable 😊

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
