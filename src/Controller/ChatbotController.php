<?php

namespace App\Controller;

use App\Service\GeminiChatbotService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;

class ChatbotController extends AbstractController
{
    private const SESSION_KEY = 'chatbot_history';
    private const MAX_HISTORY = 10;

    public function __construct(private RequestStack $requestStack) {}

    #[Route('/api/chatbot', name: 'api_chatbot', methods: ['POST'])]
    public function chat(Request $request, GeminiChatbotService $chatbotService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['message']) || empty(trim($data['message']))) {
            return $this->json(['success' => false, 'message' => 'Por favor envía un mensaje válido.'], 400);
        }

        $userMessage = trim($data['message']);
        $session = $this->requestStack->getSession();
        $history = $session->get(self::SESSION_KEY, []);

        $response = $chatbotService->chat($userMessage, $history);

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
}
