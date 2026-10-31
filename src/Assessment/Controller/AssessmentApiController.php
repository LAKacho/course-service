<?php
namespace App\Assessment\Controller;
use App\Assessment\Service\{AssessmentService, AssessmentView};
use App\Service\CurrentUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/me', defaults: ['_format' => 'json'])]
class AssessmentApiController extends AbstractController
{
    public function __construct(private AssessmentService $service, private AssessmentView $view, private CurrentUser $user) {}
    #[Route('/assessments', name: 'assessment_api_catalog', methods: ['GET'])]
    public function index(): JsonResponse { return $this->response($this->view->catalog()); }
    #[Route('/assessment-attempts/{id}', name: 'assessment_api_attempt', requirements: ['id' => '[a-f0-9]{32}'], methods: ['GET'])]
    public function attempt(string $id): JsonResponse { return $this->response($this->view->attempt($this->service->owned($id, $this->user->id()))); }
    #[Route('/assessment-attempts/{id}/result', name: 'assessment_api_result', requirements: ['id' => '[a-f0-9]{32}'], methods: ['GET'])]
    public function result(string $id): JsonResponse
    {
        try { return $this->response($this->view->result($this->service->owned($id, $this->user->id()))); }
        catch (ConflictHttpException) { return $this->response(['error' => 'Результат появится после завершения теста.'], 409); }
    }
    private function response(array $data, int $status = 200): JsonResponse
    {
        $response = $this->json($data, $status); $response->headers->set('Cache-Control', 'private, no-store'); return $response;
    }
}
