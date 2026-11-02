<?php
namespace App\Learning\Controller;
use App\Learning\Domain\Entity\Course;
use App\Learning\Domain\Entity\Sprint;
use App\Learning\Domain\Entity\Card;
use App\Learning\Domain\Entity\Task;
use App\Learning\Domain\Entity\LearningItem;
use App\Learning\Infrastructure\Persistence\LearningRepository;
use App\Learning\Application\Service\ProgressService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
#[Route('/api', defaults: ['_format' => 'json'])]
class ApiController extends AbstractController
{
    public function __construct(private LearningRepository $repository, private ProgressService $progress) {}
    #[Route('/courses', methods: ['GET'])]
    public function courses(): JsonResponse { return $this->json(array_map($this->serialize(...), $this->repository->courses())); }
    #[Route('/courses/{id}', methods: ['GET'])]
    public function course(Course $course): JsonResponse { return $this->json($this->serialize($course)); }
    #[Route('/courses/{id}/sprints', methods: ['GET'])]
    public function sprints(Course $course): JsonResponse { return $this->json(array_map($this->serialize(...), $this->repository->sprints($course))); }
    #[Route('/sprints/{id}/cards', methods: ['GET'])]
    public function cards(Sprint $sprint): JsonResponse { return $this->json(array_map($this->serialize(...), $this->repository->cards($sprint))); }
    #[Route('/cards/{id}/tasks', methods: ['GET'])]
    public function tasks(Card $card): JsonResponse { return $this->json(array_map($this->serialize(...), $this->repository->tasks($card))); }
    #[Route('/courses/{id}/progress', methods: ['GET'])]
    public function progress(Course $course): JsonResponse { return $this->json(['courseId' => $course->id] + $this->progress->course($course)); }
    private function serialize(LearningItem $item): array {
        $data = ['id' => $item->id, 'title' => $item->title, 'description' => $item->description];
        return $data + match (true) {
            $item instanceof Course => ['status' => $item->status, 'createdAt' => $item->createdAt->format(DATE_ATOM), 'updatedAt' => $item->updatedAt->format(DATE_ATOM)],
            $item instanceof Sprint => ['courseId' => $item->course->id, 'position' => $item->position],
            $item instanceof Card => ['sprintId' => $item->sprint->id, 'status' => $item->status, 'position' => $item->position],
            $item instanceof Task => ['cardId' => $item->card->id, 'type' => $item->type, 'contentUrl' => $item->contentUrl, 'position' => $item->position],
        };
    }
}
