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
use Symfony\Contracts\HttpClient\ChunkInterface;
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

    private function makeTextChunk(string $text): \PHPUnit\Framework\MockObject\MockObject
    {
        $data = json_encode(['choices' => [['delta' => ['content' => $text]]]]);
        $chunk = $this->createMock(ChunkInterface::class);
        $chunk->method('isTimeout')->willReturn(false);
        $chunk->method('isLast')->willReturn(false);
        $chunk->method('getContent')->willReturn("data: {$data}\ndata: [DONE]\n");
        return $chunk;
    }

    private function makeStreamResponse(): \PHPUnit\Framework\MockObject\MockObject
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        return $response;
    }

    private function mockStreamingText(string $text = '¡Hola! ¿En qué te puedo ayudar?'): void
    {
        $response = $this->makeStreamResponse();
        $chunk    = $this->makeTextChunk($text);
        $this->httpClient->method('request')->willReturn($response);
        $this->httpClient->method('stream')->willReturn(new MockResponseStream([$chunk]));
    }

    /**
     * Sets up httpClient to stream a text response and capture the outgoing messages array.
     * Returns a reference — populate $capturedMessages BEFORE calling chat().
     */
    private function mockStreamingWithCapture(string $text, array &$capturedMessages): void
    {
        $response = $this->makeStreamResponse();
        $chunk    = $this->makeTextChunk($text);
        $this->httpClient
            ->method('request')
            ->willReturnCallback(static function ($method, $url, $options) use (&$capturedMessages, $response) {
                $capturedMessages = $options['json']['messages'] ?? [];
                return $response;
            });
        $this->httpClient->method('stream')->willReturn(new MockResponseStream([$chunk]));
    }

    // --- Basic chat tests ---

    public function testChatReturnsSuccessResponse(): void
    {
        $this->courseRepository
            ->method('findActiveForContext')
            ->willReturn([$this->createCourse()]);

        $this->mockStreamingText('¡Hola! ¿Cómo estás?');

        $result = $this->service->chat('Hola');

        $this->assertTrue($result['success']);
        $this->assertEquals('¡Hola! ¿Cómo estás?', $result['message']);
    }

    public function testChatReturnsErrorWhenNoTextInResponse(): void
    {
        $this->courseRepository
            ->method('findActiveForContext')
            ->willReturn([]);

        // An empty stream triggers the 'openrouter_empty_stream' exception path.
        $response = $this->makeStreamResponse();
        $emptyChunk = $this->createMock(ChunkInterface::class);
        $emptyChunk->method('isTimeout')->willReturn(false);
        $emptyChunk->method('isLast')->willReturn(false);
        $emptyChunk->method('getContent')->willReturn("data: [DONE]\n");

        $this->httpClient->method('request')->willReturn($response);
        $this->httpClient->method('stream')->willReturn(new MockResponseStream([$emptyChunk]));

        $result = $this->service->chat('Hola');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('ocupado', $result['message']);
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

        $capturedMessages = [];
        $this->mockStreamingWithCapture('Hola Juan!', $capturedMessages);

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

        $systemPrompt = $capturedMessages[0]['content'];
        $this->assertStringContainsString('Juan Pérez', $systemPrompt);
        $this->assertStringContainsString('3B', $systemPrompt);
        $this->assertStringContainsString('Filosofía (nivel 4)', $systemPrompt);
        $this->assertStringContainsString('Artes (nivel 5)', $systemPrompt);
        $this->assertStringContainsString('Filosofía Moderna', $systemPrompt);
        $this->assertStringContainsString('Abierto', $systemPrompt);
    }

    public function testChatWithoutStudentContextOmitsStudentBlock(): void
    {
        $this->courseRepository->method('findActiveForContext')->willReturn([]);

        $capturedMessages = [];
        $this->mockStreamingWithCapture('Hola!', $capturedMessages);

        $result = $this->service->chat('Hola', []);

        $this->assertTrue($result['success']);

        $systemPrompt = $capturedMessages[0]['content'];
        $this->assertStringNotContainsString('CONTEXTO DEL ESTUDIANTE', $systemPrompt);
        $this->assertStringContainsString('ElectivoBot', $systemPrompt);
    }

    public function testChatWithEmptyEnrollmentsShowsNinguno(): void
    {
        $this->courseRepository->method('findActiveForContext')->willReturn([]);

        $capturedMessages = [];
        $this->mockStreamingWithCapture('Ok', $capturedMessages);

        $studentContext = [
            'name' => 'María López',
            'grade' => '2A',
            'interests' => [],
            'enrolledCourses' => [],
            'enrollmentOpen' => false,
        ];

        $this->service->chat('Hola', [], $studentContext);

        $systemPrompt = $capturedMessages[0]['content'];
        $this->assertStringContainsString('Sin intereses registrados aún', $systemPrompt);
        $this->assertStringContainsString('Ninguno', $systemPrompt);
        $this->assertStringContainsString('Cerrado', $systemPrompt);
    }

    public function testChatWithEnrollmentClosedShowsCerrado(): void
    {
        $this->courseRepository->method('findActiveForContext')->willReturn([]);

        $capturedMessages = [];
        $this->mockStreamingWithCapture('Ok', $capturedMessages);

        $studentContext = [
            'name' => 'Carlos Ruiz',
            'grade' => '4B',
            'interests' => ['Ciencias' => 5],
            'enrolledCourses' => [
                ['id' => 2, 'name' => 'Biología Avanzada', 'category' => 'Ciencias'],
            ],
            'enrollmentOpen' => false,
        ];

        $this->service->chat('Hola', [], $studentContext);

        $systemPrompt = $capturedMessages[0]['content'];
        $this->assertStringContainsString('Cerrado', $systemPrompt);
    }

    public function testSystemPromptContainsOffTopicRefusalRule(): void
    {
        $this->courseRepository->method('findActiveForContext')->willReturn([]);

        $capturedMessages = [];
        $this->mockStreamingWithCapture('Ok', $capturedMessages);

        $this->service->chat('Hola');

        $systemPrompt = $capturedMessages[0]['content'];
        $this->assertStringContainsString('temas no relacionados', $systemPrompt);
        $this->assertStringContainsString('¡Ups!', $systemPrompt);
        $this->assertStringContainsString('cursos electivos', $systemPrompt);
    }

    public function testChatIncludesHistoryInContents(): void
    {
        $this->courseRepository->method('findActiveForContext')->willReturn([]);

        $capturedMessages = [];
        $this->mockStreamingWithCapture('Respuesta', $capturedMessages);

        $history = [
            ['user' => 'Hola', 'bot' => '¡Hola! ¿En qué te puedo ayudar?'],
            ['user' => '¿Qué cursos hay?', 'bot' => 'Tenemos varios cursos disponibles.'],
        ];

        $result = $this->service->chat('Quiero inscribirme', $history);

        $this->assertTrue($result['success']);

        // system + 2 history turns (user+assistant each) + current = 6
        $this->assertCount(6, $capturedMessages);
        $this->assertEquals('user', $capturedMessages[1]['role']);
        $this->assertEquals('Hola', $capturedMessages[1]['content']);
        $this->assertEquals('assistant', $capturedMessages[2]['role']);
        $this->assertEquals('¡Hola! ¿En qué te puedo ayudar?', $capturedMessages[2]['content']);
    }

    public function testChatWithStudentContextAndHistory(): void
    {
        $this->courseRepository->method('findActiveForContext')->willReturn([]);

        $capturedMessages = [];
        $this->mockStreamingWithCapture('Respuesta', $capturedMessages);

        $history = [['user' => 'Hola', 'bot' => '¡Hola Juan!']];

        $studentContext = [
            'name' => 'Juan Pérez',
            'grade' => '3B',
            'interests' => ['Filosofía' => 4],
            'enrolledCourses' => [],
            'enrollmentOpen' => true,
        ];

        $result = $this->service->chat('¿Qué más?', $history, $studentContext);

        $this->assertTrue($result['success']);

        // system + 1 history turn (user+assistant) + current = 4
        $this->assertCount(4, $capturedMessages);
        $systemPrompt = $capturedMessages[0]['content'];
        $this->assertStringContainsString('Juan Pérez', $systemPrompt);
    }

    public function testSystemPromptContainsUnenrollRefusal(): void
    {
        $this->courseRepository->method('findActiveForContext')->willReturn([]);

        $capturedMessages = [];
        $this->mockStreamingWithCapture('Ok', $capturedMessages);

        $this->service->chat('Hola');

        $systemPrompt = $capturedMessages[0]['content'];
        $this->assertStringContainsString('perfil', $systemPrompt);
    }
}
