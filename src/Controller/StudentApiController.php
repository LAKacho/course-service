<?php
namespace App\Controller;

use App\Repository\StudentLearningRepository;
use App\Service\{CurrentUser, QuestAvailabilityService};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/me', defaults: ['_format' => 'json'])]
class StudentApiController extends AbstractController
{
    public function __construct(private CurrentUser $user, private QuestAvailabilityService $availability, private StudentLearningRepository $repository) {}

    #[Route('/learning-paths', name: 'student_api_programs', methods: ['GET'])]
    public function programs(): JsonResponse { return $this->privateJson($this->repository->programs($this->user->id())); }

    #[Route('/quests', name: 'student_api_quests', methods: ['GET'])]
    public function quests(): JsonResponse { return $this->privateJson($this->availability->visibleQuests($this->user->id())); }

    #[Route('/progress', name: 'student_api_progress', methods: ['GET'])]
    public function progress(): JsonResponse
    {
        return $this->privateJson($this->availability->visibleProgress($this->availability->visibleQuests($this->user->id())));
    }

    private function privateJson(array $data): JsonResponse
    {
        $response = $this->json($data);
        $response->headers->set('Cache-Control', 'private, no-store');
        return $response;
    }
}
