<?php

namespace App\Controller;

use App\Service\GeminiChatbotService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ChatbotController extends AbstractController
{
    #[Route('/api/chatbot', name: 'api_chatbot', methods: ['POST'])]
    public function chat(Request $request, GeminiChatbotService $chatbotService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['message']) || empty(trim($data['message']))) {
            return $this->json([
                'success' => false,
                'message' => 'Por favor envía un mensaje válido.'
            ], 400);
        }

        $userMessage = trim($data['message']);
        $userId = $this->getUser()?->getId();
        
        $response = $chatbotService->chat($userMessage, $userId);
        
        return $this->json($response);
    }
}
