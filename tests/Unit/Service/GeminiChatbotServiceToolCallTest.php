<?php

namespace App\Tests\Unit\Service;

use App\Entity\Course;
use App\Entity\User;
use App\Repository\CourseRepository;
use App\Service\EnrollmentService;
use App\Service\GeminiChatbotService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
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

    private function createFunctionCallResponse(string $toolName, array $args): array
    {
        return [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['functionCall' => ['name' => $toolName, 'args' => $args]],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function createTextResponse(string $text): array
    {
        return [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => $text],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function createMockResponse(array $data): \PHPUnit\Framework\MockObject\MockObject
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn($data);
        return $response;
    }

    // --- Tool call loop tests ---

    public function testSingleToolCallReturnsTextResponse(): void
    {
        $this->enrollmentService
            ->method('getAvailableCourses')
            ->with(category: null, search: null)
            ->willReturn([
                'courses' => [
                    ['id' => 1, 'name' => 'Filosofía Moderna', 'category' => 'Filosofía', 'teacher' => 'Prof. García', 'schedule' => 'Lun 10:00', 'capacity' => '18/25', 'spots_available' => 7],
                ],
                'total' => 1,
            ]);

        $callCount = 0;
        $this->httpClient
            ->method('request')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    return $this->createMockResponse($this->createFunctionCallResponse('get_available_courses', []));
                }
                return $this->createMockResponse($this->createTextResponse('Tenemos Filosofía Moderna disponible con 7 cupos.'));
            });

        $result = $this->service->chat('¿Qué cursos hay?');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Filosofía Moderna', $result['message']);
        $this->assertEquals(2, $callCount);
    }

    public function testChainedToolCalls(): void
    {
        $this->enrollmentService
            ->method('getAvailableCourses')
            ->willReturn([
                'courses' => [
                    ['id' => 1, 'name' => 'Filosofía Moderna', 'category' => 'Filosofía', 'teacher' => 'Prof. García', 'schedule' => 'Lun 10:00', 'capacity' => '18/25', 'spots_available' => 7],
                ],
                'total' => 1,
            ]);

        $user = $this->createUser();

        $this->enrollmentService
            ->method('getCourseDetails')
            ->with(courseId: 1, student: $user)
            ->willReturn([
                'id' => 1,
                'name' => 'Filosofía Moderna',
                'description' => 'Exploración filosófica',
                'category' => 'Filosofía',
                'teacher' => 'Prof. García',
                'schedule' => 'Lun 10:00',
                'capacity' => '18/25',
                'spots_available' => 7,
                'enrolled' => false,
                'can_enroll' => true,
                'enrollment_message' => '',
            ]);

        $callCount = 0;
        $this->httpClient
            ->method('request')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    return $this->createMockResponse($this->createFunctionCallResponse('get_available_courses', []));
                }
                if ($callCount === 2) {
                    return $this->createMockResponse($this->createFunctionCallResponse('get_course_details', ['course_id' => 1]));
                }
                return $this->createMockResponse($this->createTextResponse('Filosofía Moderna tiene 7 cupos disponibles.'));
            });

        $result = $this->service->chat('¿Qué cursos hay? Quiero ver detalles de Filosofía', [], [], $user);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('7 cupos', $result['message']);
        $this->assertEquals(3, $callCount);
    }

    public function testLoopCapReachedAtThreeIterations(): void
    {
        $this->enrollmentService
            ->method('getAvailableCourses')
            ->willReturn([
                'courses' => [],
                'total' => 0,
            ]);

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('max iterations'));

        $callCount = 0;
        $this->httpClient
            ->method('request')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                if ($callCount <= 3) {
                    return $this->createMockResponse($this->createFunctionCallResponse('get_available_courses', []));
                }
                return $this->createMockResponse($this->createTextResponse('No hay cursos.'));
            });

        $result = $this->service->chat('Dame todos los cursos');

        $this->assertTrue($result['success']);
        $this->assertEquals(3, $callCount);
    }

    public function testToolCallErrorReturnsErrorToGemini(): void
    {
        $this->enrollmentService
            ->method('getAvailableCourses')
            ->willThrowException(new \RuntimeException('DB connection failed'));

        $callCount = 0;
        $this->httpClient
            ->method('request')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    return $this->createMockResponse($this->createFunctionCallResponse('get_available_courses', []));
                }
                return $this->createMockResponse($this->createTextResponse('Hubo un error al consultar cursos.'));
            });

        $result = $this->service->chat('¿Qué cursos hay?');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('error', $result['message']);
        $this->assertEquals(2, $callCount);
    }

    // --- executeTool routing tests ---

    public function testGetAvailableCoursesForwardsFilters(): void
    {
        $this->enrollmentService
            ->expects($this->once())
            ->method('getAvailableCourses')
            ->with(category: 'Filosofía', search: 'moderna')
            ->willReturn(['courses' => [], 'total' => 0]);

        $this->httpClient
            ->method('request')
            ->willReturnCallback(function () {
                static $callCount = 0;
                $callCount++;
                if ($callCount === 1) {
                    return $this->createMockResponse($this->createFunctionCallResponse(
                        'get_available_courses',
                        ['category' => 'Filosofía', 'search' => 'moderna']
                    ));
                }
                return $this->createMockResponse($this->createTextResponse('No encontré cursos.'));
            });

        $result = $this->service->chat('Buscar cursos de filosofía moderna');

        $this->assertTrue($result['success']);
    }

    public function testGetCourseDetailsRequiresUser(): void
    {
        $this->httpClient
            ->method('request')
            ->willReturnCallback(function () {
                static $callCount = 0;
                $callCount++;
                if ($callCount === 1) {
                    return $this->createMockResponse($this->createFunctionCallResponse(
                        'get_course_details',
                        ['course_id' => 1]
                    ));
                }
                return $this->createMockResponse($this->createTextResponse('Necesito autenticación.'));
            });

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
                'id' => 1,
                'name' => 'Filosofía Moderna',
                'spots_available' => 7,
                'enrolled' => false,
                'can_enroll' => true,
                'enrollment_message' => '',
            ]);

        // enrollStudent should NOT be called — enroll_in_course is preview only
        $this->enrollmentService
            ->expects($this->never())
            ->method('enrollStudent');

        $this->httpClient
            ->method('request')
            ->willReturnCallback(function () {
                static $callCount = 0;
                $callCount++;
                if ($callCount === 1) {
                    return $this->createMockResponse($this->createFunctionCallResponse(
                        'enroll_in_course',
                        ['course_id' => 1]
                    ));
                }
                return $this->createMockResponse($this->createTextResponse('¿Te inscribís en Filosofía Moderna?'));
            });

        $result = $this->service->chat('Quiero inscribirme en Filosofía Moderna', [], [], $user);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('inscrib', $result['message']);
    }

    public function testConfirmEnrollmentActuallyEnrolls(): void
    {
        $user = $this->createUser();
        $course = $this->createCourse();

        $this->courseRepository
            ->expects($this->atLeastOnce())
            ->method('find')
            ->with(1)
            ->willReturn($course);

        $this->enrollmentService
            ->expects($this->once())
            ->method('enrollStudent')
            ->with($user, $course)
            ->willReturn(['success' => true, 'message' => 'Inscripción exitosa.']);

        $this->httpClient
            ->method('request')
            ->willReturnCallback(function () {
                static $callCount = 0;
                $callCount++;
                if ($callCount === 1) {
                    return $this->createMockResponse($this->createFunctionCallResponse(
                        'confirm_enrollment',
                        ['course_id' => 1]
                    ));
                }
                return $this->createMockResponse($this->createTextResponse('¡Inscripción exitosa!'));
            });

        $result = $this->service->chat('Sí, quiero inscribirme', [], [], $user);

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['message']);
    }

    public function testConfirmEnrollmentHandlesFailure(): void
    {
        $user = $this->createUser();
        $course = $this->createCourse(currentEnrollment: 25, maxCapacity: 25);

        $this->courseRepository
            ->method('find')
            ->with(1)
            ->willReturn($course);

        $this->enrollmentService
            ->method('enrollStudent')
            ->with($user, $course)
            ->willReturn(['success' => false, 'message' => 'No hay cupo disponible.']);

        $this->httpClient
            ->method('request')
            ->willReturnCallback(function () {
                static $callCount = 0;
                $callCount++;
                if ($callCount === 1) {
                    return $this->createMockResponse($this->createFunctionCallResponse(
                        'confirm_enrollment',
                        ['course_id' => 1]
                    ));
                }
                return $this->createMockResponse($this->createTextResponse('No se pudo inscribir.'));
            });

        $result = $this->service->chat('Sí, inscribirme', [], [], $user);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('No se pudo', $result['message']);
    }

    public function testConfirmEnrollmentCourseNotFound(): void
    {
        $user = $this->createUser();

        $this->courseRepository
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->enrollmentService
            ->expects($this->never())
            ->method('enrollStudent');

        $this->httpClient
            ->method('request')
            ->willReturnCallback(function () {
                static $callCount = 0;
                $callCount++;
                if ($callCount === 1) {
                    return $this->createMockResponse($this->createFunctionCallResponse(
                        'confirm_enrollment',
                        ['course_id' => 999]
                    ));
                }
                return $this->createMockResponse($this->createTextResponse('Curso no encontrado.'));
            });

        $result = $this->service->chat('Confirmar curso 999', [], [], $user);

        $this->assertTrue($result['success']);
    }

    public function testUnknownToolReturnsError(): void
    {
        $this->httpClient
            ->method('request')
            ->willReturnCallback(function () {
                static $callCount = 0;
                $callCount++;
                if ($callCount === 1) {
                    return $this->createMockResponse($this->createFunctionCallResponse(
                        'unknown_tool',
                        []
                    ));
                }
                return $this->createMockResponse($this->createTextResponse('No reconozco esa herramienta.'));
            });

        $result = $this->service->chat('Haz algo raro');

        $this->assertTrue($result['success']);
    }

    // --- Tool declarations in request ---

    public function testToolDeclarationsAreIncludedInRequest(): void
    {
        $capturedJson = null;

        $this->httpClient
            ->method('request')
            ->willReturnCallback(function ($method, $url, $options) use (&$capturedJson) {
                $capturedJson = $options['json'];
                return $this->createMockResponse($this->createTextResponse('Hola'));
            });

        $this->service->chat('Hola');

        $this->assertArrayHasKey('tools', $capturedJson);
        $this->assertCount(4, $capturedJson['tools']);
        $this->assertArrayHasKey('toolConfig', $capturedJson);

        $toolNames = array_map(fn($t) => $t['name'], $capturedJson['tools']);
        $this->assertContains('get_available_courses', $toolNames);
        $this->assertContains('get_course_details', $toolNames);
        $this->assertContains('enroll_in_course', $toolNames);
        $this->assertContains('confirm_enrollment', $toolNames);
    }

    // --- Tool call appended to contents ---

    public function testFunctionCallAndResponseAppendedToContents(): void
    {
        $this->enrollmentService
            ->method('getAvailableCourses')
            ->willReturn(['courses' => [], 'total' => 0]);

        $capturedContents = [];

        $this->httpClient
            ->method('request')
            ->willReturnCallback(function ($method, $url, $options) use (&$capturedContents) {
                $capturedContents = $options['json']['contents'];
                static $callCount = 0;
                $callCount++;
                if ($callCount === 1) {
                    return $this->createMockResponse($this->createFunctionCallResponse('get_available_courses', []));
                }
                return $this->createMockResponse($this->createTextResponse('No hay cursos.'));
            });

        $this->service->chat('Dame cursos');

        // After second call: system prompt + user msg + functionCall + functionResponse
        $this->assertCount(4, $capturedContents);

        // Verify functionCall in model message
        $functionCallMsg = $capturedContents[2];
        $this->assertEquals('model', $functionCallMsg['role']);
        $this->assertArrayHasKey('functionCall', $functionCallMsg['parts'][0]);

        // Verify functionResponse in user message
        $functionResponseMsg = $capturedContents[3];
        $this->assertEquals('user', $functionResponseMsg['role']);
        $this->assertArrayHasKey('functionResponse', $functionResponseMsg['parts'][0]);
        $this->assertEquals('get_available_courses', $functionResponseMsg['parts'][0]['functionResponse']['name']);
    }
}
