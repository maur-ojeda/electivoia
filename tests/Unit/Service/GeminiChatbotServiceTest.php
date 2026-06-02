<?php

namespace App\Tests\Unit\Service;

use App\Entity\Course;
use App\Entity\CourseCategory;
use App\Entity\User;
use App\Repository\CourseRepository;
use App\Service\EnrollmentService;
use App\Service\GeminiChatbotService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class GeminiChatbotServiceTest extends TestCase
{
    private GeminiChatbotService $service;
    private \PHPUnit\Framework\MockObject\MockObject $httpClient;
    private \PHPUnit\Framework\MockObject\MockObject $courseRepository;
    private \PHPUnit\Framework\MockObject\MockObject $enrollmentService;
    private \PHPUnit\Framework\MockObject\MockObject $logger;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->courseRepository = $this->createMock(CourseRepository::class);
        $this->enrollmentService = $this->createMock(EnrollmentService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new GeminiChatbotService(
            $this->httpClient,
            $this->courseRepository,
            $this->enrollmentService,
            $this->logger,
            'test-api-key',
        );
    }

    private function createCourse(
        int $id = 1,
        string $name = 'Filosofía Moderna',
        ?string $categoryName = 'Filosofía',
        ?string $teacherName = 'Prof. García',
        ?string $schedule = 'Lunes 10:00-11:30',
        int $maxCapacity = 25,
        int $currentEnrollment = 18,
    ): Course {
        $course = $this->createMock(Course::class);
        $course->method('getName')->willReturn($name);
        $course->method('getDescription')->willReturn('Exploración de corrientes filosóficas');
        $course->method('getMaxCapacity')->willReturn($maxCapacity);
        $course->method('getCurrentEnrollment')->willReturn($currentEnrollment);
        $course->method('getSchedule')->willReturn($schedule);

        $category = null;
        if ($categoryName !== null) {
            $category = $this->createMock(CourseCategory::class);
            $category->method('getName')->willReturn($categoryName);
        }
        $course->method('getCategory')->willReturn($category);

        $teacher = null;
        if ($teacherName !== null) {
            $teacher = $this->createMock(User::class);
            $teacher->method('getFullName')->willReturn($teacherName);
        }
        $course->method('getTeacher')->willReturn($teacher);

        return $course;
    }

    private function mockSuccessfulGeminiResponse(string $text = '¡Hola! ¿En qué te puedo ayudar?'): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => $text],
                        ],
                    ],
                ],
            ],
        ]);

        $this->httpClient
            ->method('request')
            ->willReturn($response);
    }

    private function mockGeminiWithCapturedContents(): array
    {
        $capturedContents = [];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'Respuesta del bot'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->httpClient
            ->method('request')
            ->willReturnCallback(function ($method, $url, $options) use (&$capturedContents) {
                $capturedContents = $options['json']['contents'] ?? [];
                return $this->createMock(ResponseInterface::class);
            });

        // We need to re-mock to also return the toArray properly
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->httpClient
            ->method('request')
            ->willReturnCallback(function ($method, $url, $options) use (&$capturedContents) {
                $capturedContents = $options['json']['contents'] ?? [];
                $response = $this->createMock(ResponseInterface::class);
                $response->method('toArray')->willReturn([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    ['text' => 'Respuesta del bot'],
                                ],
                            ],
                        ],
                    ],
                ]);
                return $response;
            });

        $this->service = new GeminiChatbotService(
            $this->httpClient,
            $this->courseRepository,
            $this->enrollmentService,
            $this->logger,
            'test-api-key',
        );

        return $capturedContents;
    }

    // --- Basic chat tests ---

    public function testChatReturnsSuccessResponse(): void
    {
        $this->courseRepository
            ->method('findActiveForContext')
            ->willReturn([$this->createCourse()]);

        $this->mockSuccessfulGeminiResponse('¡Hola! ¿Cómo estás?');

        $result = $this->service->chat('Hola');

        $this->assertTrue($result['success']);
        $this->assertEquals('¡Hola! ¿Cómo estás?', $result['message']);
    }

    public function testChatReturnsErrorWhenNoTextInResponse(): void
    {
        $this->courseRepository
            ->method('findActiveForContext')
            ->willReturn([]);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['candidates' => []]);

        $this->httpClient->method('request')->willReturn($response);

        $result = $this->service->chat('Hola');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('reformularlo', $result['message']);
    }

    public function testChatHandlesRateLimitError(): void
    {
        $this->courseRepository
            ->method('findActiveForContext')
            ->willReturn([]);

        $this->httpClient
            ->method('request')
            ->willThrowException(new \RuntimeException('429 Too Many Requests'));

        $result = $this->service->chat('Hola');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('ocupado', $result['message']);
    }

    public function testChatHandlesGenericException(): void
    {
        $this->courseRepository
            ->method('findActiveForContext')
            ->willReturn([]);

        $this->httpClient
            ->method('request')
            ->willThrowException(new \RuntimeException('Connection failed'));

        $result = $this->service->chat('Hola');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('problemas técnicos', $result['message']);
    }

    // --- Student context tests ---

    public function testChatWithStudentContextIncludesNameInPrompt(): void
    {
        $this->courseRepository
            ->method('findActiveForContext')
            ->willReturn([]);

        $capturedContents = [];
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [['text' => 'Hola Juan!']],
                    ],
                ],
            ],
        ]);

        $this->httpClient
            ->method('request')
            ->willReturnCallback(function ($method, $url, $options) use (&$capturedContents) {
                $capturedContents = $options['json']['contents'] ?? [];
                $response = $this->createMock(ResponseInterface::class);
                $response->method('toArray')->willReturn([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [['text' => 'Hola Juan!']],
                            ],
                        ],
                    ],
                ]);
                return $response;
            });

        $studentContext = [
            'name' => 'Juan Pérez',
            'grade' => '3B',
            'interests' => ['Filosofía' => 4, 'Artes' => 5],
            'enrolledCourses' => [
                ['id' => 1, 'name' => 'Filosofía Moderna', 'category' => 'Filosofía'],
            ],
            'enrollmentOpen' => true,
        ];

        $result = $this->service->chat('¿Qué cursos me recomiendas?', [], $studentContext);

        $this->assertTrue($result['success']);

        // Verify the system prompt (first message) contains student context
        $systemPrompt = $capturedContents[0]['parts'][0]['text'];
        $this->assertStringContainsString('Juan Pérez', $systemPrompt);
        $this->assertStringContainsString('3B', $systemPrompt);
        $this->assertStringContainsString('Filosofía (nivel 4)', $systemPrompt);
        $this->assertStringContainsString('Artes (nivel 5)', $systemPrompt);
        $this->assertStringContainsString('Filosofía Moderna', $systemPrompt);
        $this->assertStringContainsString('Abierto', $systemPrompt);
    }

    public function testChatWithoutStudentContextOmitsStudentBlock(): void
    {
        $this->courseRepository
            ->method('findActiveForContext')
            ->willReturn([]);

        $capturedContents = [];

        $this->httpClient
            ->method('request')
            ->willReturnCallback(function ($method, $url, $options) use (&$capturedContents) {
                $capturedContents = $options['json']['contents'] ?? [];
                $response = $this->createMock(ResponseInterface::class);
                $response->method('toArray')->willReturn([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [['text' => 'Hola!']],
                            ],
                        ],
                    ],
                ]);
                return $response;
            });

        $result = $this->service->chat('Hola', []);

        $this->assertTrue($result['success']);

        $systemPrompt = $capturedContents[0]['parts'][0]['text'];
        $this->assertStringNotContainsString('CONTEXTO DEL ESTUDIANTE', $systemPrompt);
        $this->assertStringContainsString('ElectivoBot', $systemPrompt);
    }

    public function testChatWithEmptyEnrollmentsShowsNinguno(): void
    {
        $this->courseRepository
            ->method('findActiveForContext')
            ->willReturn([]);

        $capturedContents = [];

        $this->httpClient
            ->method('request')
            ->willReturnCallback(function ($method, $url, $options) use (&$capturedContents) {
                $capturedContents = $options['json']['contents'] ?? [];
                $response = $this->createMock(ResponseInterface::class);
                $response->method('toArray')->willReturn([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [['text' => 'Ok']],
                            ],
                        ],
                    ],
                ]);
                return $response;
            });

        $studentContext = [
            'name' => 'María López',
            'grade' => '2A',
            'interests' => [],
            'enrolledCourses' => [],
            'enrollmentOpen' => false,
        ];

        $result = $this->service->chat('Hola', [], $studentContext);

        $systemPrompt = $capturedContents[0]['parts'][0]['text'];
        $this->assertStringContainsString('Sin intereses registrados aún', $systemPrompt);
        $this->assertStringContainsString('Ninguno', $systemPrompt);
        $this->assertStringContainsString('Cerrado', $systemPrompt);
    }

    public function testChatWithEnrollmentClosedShowsCerrado(): void
    {
        $this->courseRepository
            ->method('findActiveForContext')
            ->willReturn([]);

        $capturedContents = [];

        $this->httpClient
            ->method('request')
            ->willReturnCallback(function ($method, $url, $options) use (&$capturedContents) {
                $capturedContents = $options['json']['contents'] ?? [];
                $response = $this->createMock(ResponseInterface::class);
                $response->method('toArray')->willReturn([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [['text' => 'Ok']],
                            ],
                        ],
                    ],
                ]);
                return $response;
            });

        $studentContext = [
            'name' => 'Carlos Ruiz',
            'grade' => '4B',
            'interests' => ['Ciencias' => 5],
            'enrolledCourses' => [
                ['id' => 2, 'name' => 'Biología Avanzada', 'category' => 'Ciencias'],
            ],
            'enrollmentOpen' => false,
        ];

        $result = $this->service->chat('Hola', [], $studentContext);

        $systemPrompt = $capturedContents[0]['parts'][0]['text'];
        $this->assertStringContainsString('Cerrado', $systemPrompt);
    }

    public function testSystemPromptContainsOffTopicRefusalRule(): void
    {
        $this->courseRepository
            ->method('findActiveForContext')
            ->willReturn([]);

        $capturedContents = [];

        $this->httpClient
            ->method('request')
            ->willReturnCallback(function ($method, $url, $options) use (&$capturedContents) {
                $capturedContents = $options['json']['contents'] ?? [];
                $response = $this->createMock(ResponseInterface::class);
                $response->method('toArray')->willReturn([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [['text' => 'Ok']],
                            ],
                        ],
                    ],
                ]);
                return $response;
            });

        $result = $this->service->chat('Hola');

        $systemPrompt = $capturedContents[0]['parts'][0]['text'];
        $this->assertStringContainsString('temas no relacionados', $systemPrompt);
        $this->assertStringContainsString('¡Ups!', $systemPrompt);
        $this->assertStringContainsString('cursos electivos', $systemPrompt);
    }

    public function testChatIncludesHistoryInContents(): void
    {
        $this->courseRepository
            ->method('findActiveForContext')
            ->willReturn([]);

        $capturedContents = [];

        $this->httpClient
            ->method('request')
            ->willReturnCallback(function ($method, $url, $options) use (&$capturedContents) {
                $capturedContents = $options['json']['contents'] ?? [];
                $response = $this->createMock(ResponseInterface::class);
                $response->method('toArray')->willReturn([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [['text' => 'Respuesta']],
                            ],
                        ],
                    ],
                ]);
                return $response;
            });

        $history = [
            ['user' => 'Hola', 'bot' => '¡Hola! ¿En qué te puedo ayudar?'],
            ['user' => '¿Qué cursos hay?', 'bot' => 'Tenemos varios cursos disponibles.'],
        ];

        $result = $this->service->chat('Quiero inscribirme', $history);

        $this->assertTrue($result['success']);

        // system prompt + 2 history turns (user+model each) + current message = 6
        $this->assertCount(6, $capturedContents);
        $this->assertEquals('user', $capturedContents[1]['role']);
        $this->assertEquals('Hola', $capturedContents[1]['parts'][0]['text']);
        $this->assertEquals('model', $capturedContents[2]['role']);
        $this->assertEquals('¡Hola! ¿En qué te puedo ayudar?', $capturedContents[2]['parts'][0]['text']);
    }

    public function testChatWithStudentContextAndHistory(): void
    {
        $this->courseRepository
            ->method('findActiveForContext')
            ->willReturn([]);

        $capturedContents = [];

        $this->httpClient
            ->method('request')
            ->willReturnCallback(function ($method, $url, $options) use (&$capturedContents) {
                $capturedContents = $options['json']['contents'] ?? [];
                $response = $this->createMock(ResponseInterface::class);
                $response->method('toArray')->willReturn([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [['text' => 'Respuesta']],
                            ],
                        ],
                    ],
                ]);
                return $response;
            });

        $history = [
            ['user' => 'Hola', 'bot' => '¡Hola Juan!'],
        ];

        $studentContext = [
            'name' => 'Juan Pérez',
            'grade' => '3B',
            'interests' => ['Filosofía' => 4],
            'enrolledCourses' => [],
            'enrollmentOpen' => true,
        ];

        $result = $this->service->chat('¿Qué más?', $history, $studentContext);

        $this->assertTrue($result['success']);

        // system prompt + 1 history turn (user+model) + current message = 4
        $this->assertCount(4, $capturedContents);

        // Verify student name in system prompt
        $systemPrompt = $capturedContents[0]['parts'][0]['text'];
        $this->assertStringContainsString('Juan Pérez', $systemPrompt);
    }

    public function testSystemPromptContainsUnenrollRefusal(): void
    {
        $this->courseRepository
            ->method('findActiveForContext')
            ->willReturn([]);

        $capturedContents = [];

        $this->httpClient
            ->method('request')
            ->willReturnCallback(function ($method, $url, $options) use (&$capturedContents) {
                $capturedContents = $options['json']['contents'] ?? [];
                $response = $this->createMock(ResponseInterface::class);
                $response->method('toArray')->willReturn([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [['text' => 'Ok']],
                            ],
                        ],
                    ],
                ]);
                return $response;
            });

        $result = $this->service->chat('Hola');

        $systemPrompt = $capturedContents[0]['parts'][0]['text'];
        // The prompt should contain instructions to NOT handle unenrollment
        $this->assertStringContainsString('perfil', $systemPrompt);
    }
}
