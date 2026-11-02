<?php
namespace App\Learning\Controller;

use App\Learning\Domain\Entity\LearningPath;

use App\Learning\Domain\Entity\PathCourse;
use App\Learning\Infrastructure\Persistence\LearningPathRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/learning-paths', defaults: ['_format' => 'json'])]
class LearningPathApiController extends AbstractController
{
    public function __construct(private LearningPathRepository $repository) {}

    #[Route('', name: 'api_learning_paths', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json(array_map($this->serialize(...), $this->repository->all()));
    }

    #[Route('/{id}', name: 'api_learning_path', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(LearningPath $path): JsonResponse
    {
        return $this->json($this->serialize($path));
    }

    #[Route('/{id}/courses', name: 'api_learning_path_courses', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function courses(LearningPath $path): JsonResponse
    {
        return $this->json(array_map(static fn(PathCourse $link): array => [
            'id' => $link->course->id,
            'title' => $link->course->title,
            'description' => $link->course->description,
            'status' => $link->course->status,
            'position' => $link->position,
            'pathCourseId' => $link->id,
        ], $this->repository->courses($path)));
    }

    private function serialize(LearningPath $path): array
    {
        return [
            'id' => $path->id, 'title' => $path->title, 'slug' => $path->slug,
            'description' => $path->description, 'status' => $path->status,
            'createdAt' => $path->createdAt->format(DATE_ATOM), 'updatedAt' => $path->updatedAt->format(DATE_ATOM),
        ];
    }
}
