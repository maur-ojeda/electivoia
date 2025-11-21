<?php

namespace App\Service;

use App\Repository\CourseRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class GeminiChatbotService
{
    private const GEMINI_API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent';
    
    public function __construct(
        private HttpClientInterface $httpClient,
        private CourseRepository $courseRepository,
        private LoggerInterface $logger,
        private string $geminiApiKey
    ) {
    }

    public function chat(string $userMessage, ?int $userId = null): array
    {
        try {
            // Obtener información de los cursos disponibles
            $coursesContext = $this->buildCoursesContext();
            
            // Construir el prompt con contexto
            $systemPrompt = $this->buildSystemPrompt($coursesContext);
            
            // Llamar a la API de Gemini
            $response = $this->httpClient->request('POST', self::GEMINI_API_URL, [
                'query' => ['key' => $this->geminiApiKey],
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $systemPrompt],
                                ['text' => "Usuario: " . $userMessage]
                            ]
                        ]
                    ],
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
            
            return [
                'success' => false,
                'message' => 'Lo siento, estoy teniendo problemas técnicos. Por favor intenta más tarde.'
            ];
        }
    }

    private function buildCoursesContext(): string
    {
        $courses = $this->courseRepository->findAll();
        $context = "CURSOS DISPONIBLES:\n\n";
        
        foreach ($courses as $course) {
            $context .= sprintf(
                "- %s (Categoría: %s)\n  Descripción: %s\n  Profesor: %s\n  Cupos: %d\n\n",
                $course->getName(),
                $course->getCategory()?->getName() ?? 'Sin categoría',
                $course->getDescription() ?? 'Sin descripción',
                $course->getTeacher()?->getFullName() ?? 'Por asignar',
                $course->getCapacity()
            );
        }
        
        return $context;
    }

    private function buildSystemPrompt(string $coursesContext): string
    {
        return <<<PROMPT
Eres un asistente virtual amigable y útil del sistema de cursos electivos de un colegio chileno.

Tu objetivo es ayudar a los estudiantes a:
1. Descubrir cursos que les puedan interesar
2. Responder preguntas sobre los cursos disponibles
3. Recomendar cursos según sus intereses
4. Proporcionar información sobre profesores, horarios y cupos

INFORMACIÓN DE CURSOS DISPONIBLES:
{$coursesContext}

INSTRUCCIONES:
- Sé amigable, cercano y motivador
- Usa lenguaje apropiado para estudiantes de enseñanza media
- Si te preguntan por un curso específico, busca en la lista y proporciona detalles
- Si te piden recomendaciones, pregunta por sus intereses primero
- Si no sabes algo, sé honesto y sugiere contactar a un profesor o administrador
- Mantén las respuestas concisas pero informativas (máximo 3-4 párrafos)
- Usa emojis ocasionalmente para ser más amigable 😊

PROMPT;
    }
}
