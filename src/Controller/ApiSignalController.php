<?php

namespace App\Controller;

use App\Entity\AiSignal;
use App\Entity\Course;
use App\Service\SignalCollectionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ApiSignalController extends AbstractController
{
    #[Route('/api/ai/signal', name: 'api_ai_signal', methods: ['POST'])]
    public function record(
        Request $request,
        SignalCollectionService $signalService,
        EntityManagerInterface $em,
    ): JsonResponse {
        $user = $this->getUser();

        if ($user === null) {
            return $this->json(
                ['success' => false, 'error' => 'Authentication required'],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json(
                ['success' => false, 'error' => 'Invalid JSON body'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $action = $data['action'] ?? null;

        if ($action === null || !in_array($action, AiSignal::ALLOWED_ACTIONS, true)) {
            return $this->json(
                ['success' => false, 'error' => 'Invalid or missing action'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $course = null;
        $courseId = $data['course_id'] ?? null;

        if ($courseId !== null) {
            $course = $em->getRepository(Course::class)->find($courseId);

            if ($course === null) {
                return $this->json(
                    ['success' => false, 'error' => 'Course not found'],
                    Response::HTTP_NOT_FOUND,
                );
            }
        }

        $metadata = $data['metadata'] ?? [];

        $signalService->record($user, $action, $course, $metadata);
        $em->flush();

        return $this->json(['success' => true], Response::HTTP_CREATED);
    }
}
