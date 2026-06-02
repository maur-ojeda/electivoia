<?php

namespace App\Controller;

use App\Repository\EnrollmentRepository;
use App\Service\GeminiChatbotService;
use App\Service\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;

class ChatbotController extends AbstractController
{
    private const SESSION_KEY = 'chatbot_history';
    private const MAX_HISTORY = 10;

    public function __construct(
        private RequestStack $requestStack,
        private TenantContext $tenantContext,
        private EnrollmentRepository $enrollmentRepository,
    ) {}

    #[Route('/api/chatbot', name: 'api_chatbot', methods: ['POST'])]
    public function chat(Request $request, GeminiChatbotService $chatbotService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['message']) || empty(trim($data['message']))) {
            return $this->json(['success' => false, 'message' => 'Por favor envía un mensaje válido.'], 400);
        }

        $user = $this->getUser();
        if ($user === null) {
            return $this->json(['success' => false, 'message' => 'Debes iniciar sesión para usar el asistente.'], 401);
        }

        $studentContext = $this->buildStudentContext($user);

        $userMessage = trim($data['message']);
        $session = $this->requestStack->getSession();
        $history = $session->get(self::SESSION_KEY, []);

        $response = $chatbotService->chat($userMessage, $history, $studentContext);

        if ($response['success']) {
            $history[] = ['user' => $userMessage, 'bot' => $response['message']];
            if (count($history) > self::MAX_HISTORY) {
                $history = array_slice($history, -self::MAX_HISTORY);
            }
            $session->set(self::SESSION_KEY, $history);
        }

        return $this->json($response);
    }

    #[Route('/api/chatbot/clear', name: 'api_chatbot_clear', methods: ['POST'])]
    public function clearHistory(): JsonResponse
    {
        $this->requestStack->getSession()->remove(self::SESSION_KEY);
        return $this->json(['success' => true]);
    }

    private function buildStudentContext(\App\Entity\User $user): array
    {
        $interestProfile = $user->getInterestProfile();
        $interests = $interestProfile?->getInterests() ?? [];

        $enrollments = $this->enrollmentRepository->findBy(['student' => $user]);
        $enrolledCourses = [];
        foreach ($enrollments as $enrollment) {
            $course = $enrollment->getCourse();
            if ($course !== null) {
                $enrolledCourses[] = [
                    'id' => $course->getId(),
                    'name' => $course->getName(),
                    'category' => $course->getCategory()?->getName(),
                ];
            }
        }

        $school = $this->tenantContext->getCurrentSchool();
        $enrollmentOpen = $school === null || $school->isEnrollmentOpen();

        return [
            'name' => $user->getFullName() ?? 'Estudiante',
            'grade' => $user->getGrade() ?? 'No especificado',
            'interests' => $interests,
            'enrolledCourses' => $enrolledCourses,
            'enrollmentOpen' => $enrollmentOpen,
        ];
    }
}
