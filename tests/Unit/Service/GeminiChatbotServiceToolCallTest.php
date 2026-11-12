<?php

namespace App\Tests\Unit\Service;

use App\Entity\Course;
use App\Entity\User;
use App\Repository\CourseRepository;
use App\Service\EnrollmentService;
use App\Service\GeminiChatbotService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class GeminiChatbotServiceToolCallTest extends TestCase
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

        $this->courseRepository
            ->method('findActiveForContext')
            ->willReturn([]);

        $this->service = new GeminiChatbotService(
            $this->httpClient,
            $this->courseRepository,
            $this->enrollmentService,
            $this->logger,
            'test-api-key',
        );
    }

    private function createUser(int $id = 1, string $identifier = 'juan@test.com'): \PHPUnit\Framework\MockObject\MockObject
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getUserIdentifier')->willReturn($identifier);
        return $user;
    }

    private function createCourse(
        int $id = 1,
        string $name = 'Filosofía Moderna',
        int $maxCapacity = 25,
        int $currentEnrollment = 18,
        bool $isActive = true,
    ): \PHPUnit\Framework\MockObject\MockObject {
        $course = $this->createMock(Course::class);
        $course->method('getId')->willReturn($id);
        $course->method('getName')->willReturn($name);
        $course->method('getMaxCapacity')->willReturn($maxCapacity);
        $course->method('getCurrentEnrollment')->willReturn($currentEnrollment);
        $course->method('isActive')->willReturn($isActive);
        return $course;
    }

    /** Returns a ResponseInterface mock representing a 200-OK streaming response. */
    private function makeStreamResponse(): \PHPUnit\Framework\MockObject\MockObject
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        return $response;
    }

    /** Builds a single SSE chunk that carries a text delta + [DONE]. */
    private function makeTextChunk(string $text): \PHPUnit\Framework\MockObject\MockObject
    {
        $data = json_encode(['choices' => [['delta' => ['content' => $text]]]]);
        $chunk = $this->createMock(ChunkInterface::class);
        $chunk->method('isTimeout')->willReturn(false);
        $chunk->method('isLast')->willReturn(false);
        $chunk->method('getContent')->willReturn("data: {$data}\ndata: [DONE]\n");
        return $chunk;
    }

    /** Builds a single SSE chunk that carries a tool-call delta + [DONE]. */
    private function makeToolCallChunk(string $toolName, array $args = []): \PHPUnit\Framework\MockObject\MockObject
    {
        $argsJson = json_encode($args);
        $data = json_encode([
            'choices' => [[
                'delta' => [
                    'tool_calls' => [[
                        'index'    => 0,
                        'id'       => 'call_' . $toolName,
                        'function' => ['name' => $toolName, 'arguments' => $argsJson],
                    ]],
                ],
            ]],
        ]);
        $chunk = $this->createMock(ChunkInterface::class);
        $chunk->method('isTimeout')->willReturn(false);
        $chunk->method('isLast')->willReturn(false);
        $chunk->method('getContent')->willReturn("data: {$data}\ndata: [DONE]\n");
        return $chunk;
    }

    /**
     * Sets up httpClient so successive request() calls return fresh responses,
     * and stream() returns the matching chunk sequence.
     *
     * @param array<array{type:'text',text:string}|array{type:'tool',name:string,args:array}> $sequence
     */
    private function setupStreamSequence(array $sequence): void
    {
        $responses = [];
        $chunkMap  = [];

        foreach ($sequence as $item) {
            $response = $this->makeStreamResponse();
            $chunk    = $item['type'] === 'text'
                ? $this->makeTextChunk($item['text'])
                : $this->makeToolCallChunk($item['name'], $item['args'] ?? []);
            $responses[]                      = $response;
            $chunkMap[spl_object_id($response)] = [$chunk];
        }

        $this->httpClient
            ->method('request')
            ->willReturnCallback(static function () use (&$responses) {
                return array_shift($responses);
            });

        $this->httpClient
            ->method('stream')
            ->willReturnCallback(static function ($resp) use ($chunkMap) {
                return new MockResponseStream($chunkMap[spl_object_id($resp)] ?? []);
            });
    }

    // --- Tool call loop tests ---

    public function testSingleToolCallReturnsTextResponse(): void
    {
        $this->enrollmentService
            ->method('getAvailableCourses')
            ->with(category: null, search: null)
            ->willReturn([
                'courses' => [['id' => 1, 'name' => 'Filosofía Moderna', 'category' => 'Filosofía', 'teacher' => 'Prof. García', 'schedule' => 'Lun 10:00', 'capacity' => '18/25', 'spots_available' => 7]],
                'total' => 1,
            ]);

        $this->setupStreamSequence([
            ['type' => 'tool', 'name' => 'get_available_courses', 'args' => []],
            ['type' => 'text', 'text' => 'Tenemos Filosofía Moderna disponible con 7 cupos.'],
        ]);

        $result = $this->service->chat('¿Qué cursos hay?');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Filosofía Moderna', $result['message']);
    }

    public function testChainedToolCalls(): void
    {
        $this->enrollmentService
            ->method('getAvailableCourses')
            ->willReturn([
                'courses' => [['id' => 1, 'name' => 'Filosofía Moderna', 'category' => 'Filosofía', 'teacher' => 'Prof. García', 'schedule' => 'Lun 10:00', 'capacity' => '18/25', 'spots_available' => 7]],
                'total' => 1,
            ]);

        $user = $this->createUser();

        $this->enrollmentService
            ->method('getCourseDetails')
            ->with(courseId: 1, student: $user)
            ->willReturn([
                'id' => 1, 'name' => 'Filosofía Moderna', 'description' => 'Exploración filosófica',
                'category' => 'Filosofía', 'teacher' => 'Prof. García', 'schedule' => 'Lun 10:00',
                'capacity' => '18/25', 'spots_available' => 7, 'enrolled' => false, 'can_enroll' => true, 'enrollment_message' => '',
            ]);

        $this->setupStreamSequence([
            ['type' => 'tool', 'name' => 'get_available_courses', 'args' => []],
            ['type' => 'tool', 'name' => 'get_course_details', 'args' => ['course_id' => 1]],
            ['type' => 'text', 'text' => 'Filosofía Moderna tiene 7 cupos disponibles.'],
        ]);

        $result = $this->service->chat('¿Qué cursos hay? Quiero ver detalles de Filosofía', [], [], $user);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('7 cupos', $result['message']);
    }

    public function testLoopCapReachedAtThreeIterations(): void
    {
        $this->enrollmentService
            ->method('getAvailableCourses')
            ->willReturn(['courses' => [], 'total' => 0]);

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('max iterations'));

        $this->setupStreamSequence([
            ['type' => 'tool', 'name' => 'get_available_courses', 'args' => []],
            ['type' => 'tool', 'name' => 'get_available_courses', 'args' => []],
            ['type' => 'tool', 'name' => 'get_available_courses', 'args' => []],
        ]);

        $result = $this->service->chat('Dame todos los cursos');

        $this->assertTrue($result['success']);
    }

    public function testToolCallErrorReturnsErrorToGemini(): void
    {
        $this->enrollmentService
            ->method('getAvailableCourses')
            ->willThrowException(new \RuntimeException('DB connection failed'));

        $this->setupStreamSequence([
            ['type' => 'tool', 'name' => 'get_available_courses', 'args' => []],
            ['type' => 'text', 'text' => 'Hubo un error al consultar cursos.'],
        ]);

        $result = $this->service->chat('¿Qué cursos hay?');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('error', $result['message']);
    }

    // --- executeTool routing tests ---

    public function testGetAvailableCoursesForwardsFilters(): void
    {
        $this->enrollmentService
            ->expects($this->once())
            ->method('getAvailableCourses')
            ->with(category: 'Filosofía', search: 'moderna')
            ->willReturn(['courses' => [], 'total' => 0]);

        $this->setupStreamSequence([
            ['type' => 'tool', 'name' => 'get_available_courses', 'args' => ['category' => 'Filosofía', 'search' => 'moderna']],
            ['type' => 'text', 'text' => 'No encontré cursos.'],
        ]);

        $result = $this->service->chat('Buscar cursos de filosofía moderna');

        $this->assertTrue($result['success']);
    }

    public function testGetCourseDetailsRequiresUser(): void
    {
        $this->setupStreamSequence([
            ['type' => 'tool', 'name' => 'get_course_details', 'args' => ['course_id' => 1]],
            ['type' => 'text', 'text' => 'Necesito autenticación.'],
        ]);

        // No user passed — currentUser is null
        $result = $this->service->chat('Detalles del curso 1');

        $this->assertTrue($result['success']);
    }

    public function testEnrollInCourseReturnsPreviewOnly(): void
    {
        $user = $this->createUser();

        $this->enrollmentService
            ->expects($this->once())
            ->method('getCourseDetails')
            ->with(courseId: 1, student: $user)
            ->willReturn([
                'id' => 1, 'name' => 'Filosofía Moderna', 'spots_available' => 7,
                'enrolled' => false, 'can_enroll' => true, 'enrollment_message' => '',
            ]);

        $this->enrollmentService->expects($this->never())->method('enrollStudent');

        $this->setupStreamSequence([
            ['type' => 'tool', 'name' => 'enroll_in_course', 'args' => ['course_id' => 1]],
            ['type' => 'text', 'text' => '¿Te inscribís en Filosofía Moderna?'],
        ]);

        $result = $this->service->chat('Quiero inscribirme en Filosofía Moderna', [], [], $user);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('inscrib', $result['message']);
    }

    public function testConfirmEnrollmentActuallyEnrolls(): void
    {
        $user   = $this->createUser();
        $course = $this->createCourse();

        $this->courseRepository->expects($this->atLeastOnce())->method('find')->with(1)->willReturn($course);
        $this->enrollmentService
            ->expects($this->once())
            ->method('enrollStudent')
            ->with($user, $course)
            ->willReturn(['success' => true, 'message' => 'Inscripción exitosa.']);

        $this->setupStreamSequence([
            ['type' => 'tool', 'name' => 'confirm_enrollment', 'args' => ['course_id' => 1]],
            ['type' => 'text', 'text' => '¡Inscripción exitosa!'],
        ]);

        $result = $this->service->chat('Sí, quiero inscribirme', [], [], $user);

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['message']);
    }

    public function testConfirmEnrollmentHandlesFailure(): void
    {
        $user   = $this->createUser();
        $course = $this->createCourse(currentEnrollment: 25, maxCapacity: 25);

        $this->courseRepository->method('find')->with(1)->willReturn($course);
        $this->enrollmentService->method('enrollStudent')->with($user, $course)
            ->willReturn(['success' => false, 'message' => 'No hay cupo disponible.']);

        $this->setupStreamSequence([
            ['type' => 'tool', 'name' => 'confirm_enrollment', 'args' => ['course_id' => 1]],
            ['type' => 'text', 'text' => 'No se pudo inscribir.'],
        ]);

        $result = $this->service->chat('Sí, inscribirme', [], [], $user);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('No se pudo', $result['message']);
    }

    public function testConfirmEnrollmentCourseNotFound(): void
    {
        $user = $this->createUser();

        $this->courseRepository->method('find')->with(999)->willReturn(null);
        $this->enrollmentService->expects($this->never())->method('enrollStudent');

        $this->setupStreamSequence([
            ['type' => 'tool', 'name' => 'confirm_enrollment', 'args' => ['course_id' => 999]],
            ['type' => 'text', 'text' => 'Curso no encontrado.'],
        ]);

        $result = $this->service->chat('Confirmar curso 999', [], [], $user);

        $this->assertTrue($result['success']);
    }

    public function testUnknownToolReturnsError(): void
    {
        $this->setupStreamSequence([
            ['type' => 'tool', 'name' => 'unknown_tool', 'args' => []],
            ['type' => 'text', 'text' => 'No reconozco esa herramienta.'],
        ]);

        $result = $this->service->chat('Haz algo raro');

        $this->assertTrue($result['success']);
    }

    // --- Tool declarations in request ---

    public function testToolDeclarationsAreIncludedInRequest(): void
    {
        $capturedJson  = null;
        $response      = $this->createMock(\Symfony\Contracts\HttpClient\ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $chunk         = $this->makeTextChunk('Hola');

        $this->httpClient
            ->method('request')
            ->willReturnCallback(function ($method, $url, $options) use (&$capturedJson, $response) {
                $capturedJson = $options['json'];
                return $response;
            });
        $this->httpClient->method('stream')->willReturn(new MockResponseStream([$chunk]));

        $this->service->chat('Hola');

        // OpenAI-format: tools is an array of {type: 'function', function: {name, ...}}
        $this->assertArrayHasKey('tools', $capturedJson);
        $this->assertCount(4, $capturedJson['tools']);

        $toolNames = array_map(fn($t) => $t['function']['name'], $capturedJson['tools']);
        $this->assertContains('get_available_courses', $toolNames);
        $this->assertContains('get_course_details', $toolNames);
        $this->assertContains('enroll_in_course', $toolNames);
        $this->assertContains('confirm_enrollment', $toolNames);

        foreach ($capturedJson['tools'] as $tool) {
            $this->assertEquals('function', $tool['type']);
        }
    }

    // --- Tool call appended to messages ---

    public function testFunctionCallAndResponseAppendedToContents(): void
    {
        $this->enrollmentService
            ->method('getAvailableCourses')
            ->willReturn(['courses' => [], 'total' => 0]);

        $capturedMessages = [];
        $callIndex        = 0;
        $responses        = [];
        $chunkMap         = [];

        $resp1  = $this->createMock(\Symfony\Contracts\HttpClient\ResponseInterface::class);
        $resp1->method('getStatusCode')->willReturn(200);
        $chunk1 = $this->makeToolCallChunk('get_available_courses', []);
        $resp2  = $this->createMock(\Symfony\Contracts\HttpClient\ResponseInterface::class);
        $resp2->method('getStatusCode')->willReturn(200);
        $chunk2 = $this->makeTextChunk('No hay cursos.');

        $responses = [$resp1, $resp2];
        $chunkMap  = [spl_object_id($resp1) => [$chunk1], spl_object_id($resp2) => [$chunk2]];

        $this->httpClient
            ->method('request')
            ->willReturnCallback(function ($m, $u, $options) use (&$capturedMessages, &$callIndex, $responses) {
                $capturedMessages = $options['json']['messages'] ?? [];
                return $responses[$callIndex++];
            });
        $this->httpClient
            ->method('stream')
            ->willReturnCallback(fn($r) => new MockResponseStream($chunkMap[spl_object_id($r)] ?? []));

        $this->service->chat('Dame cursos');

        // After second call: system + user + assistant(tool_call) + tool(result)
        $this->assertGreaterThanOrEqual(4, count($capturedMessages));

        // Find the assistant message with tool_calls
        $assistantMsg = null;
        foreach ($capturedMessages as $msg) {
            if ($msg['role'] === 'assistant' && isset($msg['tool_calls'])) {
                $assistantMsg = $msg;
                break;
            }
        }
        $this->assertNotNull($assistantMsg, 'Expected assistant message with tool_calls');
        $this->assertEquals('get_available_courses', $assistantMsg['tool_calls'][0]['function']['name']);

        // Find the tool result message
        $toolMsg = null;
        foreach ($capturedMessages as $msg) {
            if ($msg['role'] === 'tool') {
                $toolMsg = $msg;
                break;
            }
        }
        $this->assertNotNull($toolMsg, 'Expected tool result message');
    }
}
