<?php

namespace App\Tests\Integration;

use App\Controller\ChatbotController;
use App\Entity\User;
use App\Repository\EnrollmentRepository;
use App\Service\GeminiChatbotService;
use App\Service\TenantContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class ChatbotControllerTest extends TestCase
{
    public function testUnauthenticatedRequestReturns401(): void
    {
        $controller = $this->createController(user: null);
        
        $request = new Request([], [], [], [], [], [], json_encode(['message' => 'Hola']));
        $request->headers->set('Content-Type', 'application/json');
        
        $response = $controller->chat($request, $this->createMock(GeminiChatbotService::class));
        
        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('iniciar sesión', $data['message']);
    }

    public function testEmptyMessageReturns400(): void
    {
        $controller = $this->createController();
        
        $request = new Request([], [], [], [], [], [], json_encode(['message' => '']));
        $request->headers->set('Content-Type', 'application/json');
        
        $response = $controller->chat($request, $this->createMock(GeminiChatbotService::class));
        
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function testValidRequestCallsChatbotService(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getFullName')->willReturn('Juan Pérez');
        $user->method('getGrade')->willReturn('3B');
        
        $controller = $this->createController(user: $user);
        
        $chatbotService = $this->createMock(GeminiChatbotService::class);
        $chatbotService
            ->expects($this->once())
            ->method('chat')
            ->with(
                $this->equalTo('¿Qué cursos hay?'),
                $this->anything(),
                $this->callback(function ($context) {
                    return $context['name'] === 'Juan Pérez' 
                        && $context['grade'] === '3B';
                }),
                $this->identicalTo($user)
            )
            ->willReturn(['success' => true, 'message' => 'Tenemos varios cursos.']);
        
        $request = new Request([], [], [], [], [], [], json_encode(['message' => '¿Qué cursos hay?']));
        $request->headers->set('Content-Type', 'application/json');
        
        $response = $controller->chat($request, $chatbotService);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Tenemos varios cursos.', $data['message']);
    }

    public function testHistoryIsStoredInSession(): void
    {
        $controller = $this->createController();
        
        $chatbotService = $this->createMock(GeminiChatbotService::class);
        $chatbotService
            ->method('chat')
            ->willReturn(['success' => true, 'message' => 'Respuesta 1']);
        
        // First message
        $request1 = new Request([], [], [], [], [], [], json_encode(['message' => 'Hola']));
        $request1->headers->set('Content-Type', 'application/json');
        $controller->chat($request1, $chatbotService);
        
        // Verify history stored
        $session = $controller->getRequestStack()->getSession();
        $history = $session->get('chatbot_history', []);
        $this->assertCount(1, $history);
        $this->assertEquals('Hola', $history[0]['user']);
        $this->assertEquals('Respuesta 1', $history[0]['bot']);
    }

    public function testClearHistoryRemovesSessionData(): void
    {
        $controller = $this->createController();
        
        // Add some history first
        $session = $controller->getRequestStack()->getSession();
        $session->set('chatbot_history', [['user' => 'Hola', 'bot' => 'Hola!']]);
        
        $response = $controller->clearHistory();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        
        $this->assertEmpty($session->get('chatbot_history'));
    }

    public function testStudentContextIncludesEnrollmentOpen(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getFullName')->willReturn('María');
        
        $school = $this->createMock(\App\Entity\School::class);
        $school->method('isEnrollmentOpen')->willReturn(true);
        
        $tenantContext = $this->createMock(TenantContext::class);
        $tenantContext->method('getCurrentSchool')->willReturn($school);
        
        $controller = $this->createController(user: $user, tenantContext: $tenantContext);
        
        $capturedContext = null;
        $chatbotService = $this->createMock(GeminiChatbotService::class);
        $chatbotService
            ->expects($this->once())
            ->method('chat')
            ->willReturnCallback(function ($message, $history, $context) use (&$capturedContext) {
                $capturedContext = $context;
                return ['success' => true, 'message' => 'Ok'];
            });
        
        $request = new Request([], [], [], [], [], [], json_encode(['message' => 'Hola']));
        $request->headers->set('Content-Type', 'application/json');
        $controller->chat($request, $chatbotService);
        
        $this->assertTrue($capturedContext['enrollmentOpen']);
    }

    public function testStudentContextWithNoEnrollments(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getFullName')->willReturn('Pedro');
        
        $enrollmentRepo = $this->createMock(EnrollmentRepository::class);
        $enrollmentRepo->method('findBy')->willReturn([]);
        
        $controller = $this->createController(user: $user, enrollmentRepo: $enrollmentRepo);
        
        $capturedContext = null;
        $chatbotService = $this->createMock(GeminiChatbotService::class);
        $chatbotService
            ->expects($this->once())
            ->method('chat')
            ->willReturnCallback(function ($message, $history, $context) use (&$capturedContext) {
                $capturedContext = $context;
                return ['success' => true, 'message' => 'Ok'];
            });
        
        $request = new Request([], [], [], [], [], [], json_encode(['message' => 'Hola']));
        $request->headers->set('Content-Type', 'application/json');
        $controller->chat($request, $chatbotService);
        
        $this->assertEmpty($capturedContext['enrolledCourses']);
    }

    private function createController(
        ?User $user = null,
        ?TenantContext $tenantContext = null,
        ?EnrollmentRepository $enrollmentRepo = null
    ): ChatbotController {
        $requestStack = new RequestStack();
        $request = new Request();
        $request->setSession(new \Symfony\Component\HttpFoundation\Session\Session());
        $requestStack->push($request);
        
        if ($tenantContext === null) {
            $tenantContext = $this->createMock(TenantContext::class);
            $tenantContext->method('getCurrentSchool')->willReturn(null);
        }
        
        if ($enrollmentRepo === null) {
            $enrollmentRepo = $this->createMock(EnrollmentRepository::class);
            $enrollmentRepo->method('findBy')->willReturn([]);
        }
        
        $controller = new ChatbotController($requestStack, $tenantContext, $enrollmentRepo);
        
        if ($user !== null) {
            $tokenStorage = $this->createMock(TokenStorageInterface::class);
            $token = $this->createMock(TokenInterface::class);
            $token->method('getUser')->willReturn($user);
            $tokenStorage->method('getToken')->willReturn($token);
            $controller->setContainer($this->createContainerWithTokenStorage($tokenStorage));
        }
        
        return $controller;
    }

    private function createContainerWithTokenStorage(TokenStorageInterface $tokenStorage)
    {
        $container = new \Symfony\Component\DependencyInjection\Container();
        $container->set('security.token_storage', $tokenStorage);
        return $container;
    }
}
