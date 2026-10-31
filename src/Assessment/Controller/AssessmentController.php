<?php
namespace App\Assessment\Controller;
use App\Assessment\Entity\Assessment;
use App\Assessment\Form\AnswerType;
use App\Assessment\Service\{AssessmentService, AssessmentView};
use App\Service\CurrentUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;

class AssessmentController extends AbstractController
{
    public function __construct(private AssessmentService $service, private AssessmentView $view, private CurrentUser $user) {}
    #[Route('/assessments', name: 'assessment_home', methods: ['GET'])]
    public function index(): Response { return $this->privateResponse($this->render('assessment/index.html.twig', ['assessments' => $this->view->catalog()])); }
    #[Route('/assessments/{code}/start', name: 'assessment_start', requirements: ['code' => '[a-z_]+'], methods: ['POST'])]
    public function start(string $code, Request $request, EntityManagerInterface $em): Response
    {
        $this->csrf($request, 'assessment_start_'.$code);
        $assessment = $em->getRepository(Assessment::class)->findOneBy(['code' => $code]) ?? throw $this->createNotFoundException();
        $attempt = $this->service->start($assessment, $this->user->id());
        return $this->redirectToRoute('assessment_attempt', ['id' => $attempt->publicId], 303);
    }
    #[Route('/assessments/attempts/{id}', name: 'assessment_attempt', requirements: ['id' => '[a-f0-9]{32}'], methods: ['GET'])]
    public function attempt(string $id): Response
    {
        $attempt = $this->service->owned($id, $this->user->id());
        if ($attempt->status === 'completed') { return $this->redirectToRoute('assessment_result', ['id' => $id], 303); }
        $model = $this->view->attempt($attempt);
        $form = $model['currentQuestion'] ? $this->answerForm($id, $model['currentQuestion']) : null;
        return $this->privateResponse($this->render('assessment/attempt.html.twig', ['attempt' => $model, 'form' => $form]));
    }
    #[Route('/assessments/attempts/{id}/answer/{key}', name: 'assessment_answer', requirements: ['id' => '[a-f0-9]{32}', 'key' => '[a-z0-9-]+'], methods: ['POST'])]
    public function answer(string $id, string $key, Request $request): Response
    {
        $attempt = $this->service->owned($id, $this->user->id());
        $question = $this->view->questionForAnswer($attempt, $key);
        $form = $this->answerForm($id, $question)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $selected = $form->get('selection')->getData();
            try {
                $this->service->answer($attempt, $this->user->id(), $key, is_array($selected) ? $selected : [$selected]);
                return $this->redirectToRoute('assessment_attempt', ['id' => $id], 303);
            } catch (ConflictHttpException $exception) {
                return $this->privateResponse($this->render('assessment/message.html.twig', ['message' => $exception->getMessage()], new Response(status: 409)));
            }
        }
        $model = $this->view->attempt($attempt); $model['currentQuestion'] = $question;
        return $this->privateResponse($this->render('assessment/attempt.html.twig', ['attempt' => $model, 'form' => $form], new Response(status: 422)));
    }
    #[Route('/assessments/attempts/{id}/finish', name: 'assessment_finish', requirements: ['id' => '[a-f0-9]{32}'], methods: ['POST'])]
    public function finish(string $id, Request $request): Response
    {
        $this->csrf($request, 'assessment_finish_'.$id);
        $attempt = $this->service->owned($id, $this->user->id());
        try { $this->service->finish($attempt, $this->user->id()); }
        catch (ConflictHttpException $exception) { return $this->privateResponse($this->render('assessment/message.html.twig', ['message' => $exception->getMessage()], new Response(status: 409))); }
        return $this->redirectToRoute('assessment_result', ['id' => $id], 303);
    }
    #[Route('/assessments/attempts/{id}/result', name: 'assessment_result', requirements: ['id' => '[a-f0-9]{32}'], methods: ['GET'])]
    public function result(string $id): Response
    {
        $attempt = $this->service->owned($id, $this->user->id());
        try { $result = $this->view->result($attempt); }
        catch (ConflictHttpException $exception) { return $this->privateResponse($this->render('assessment/message.html.twig', ['message' => $exception->getMessage()], new Response(status: 409))); }
        return $this->privateResponse($this->render('assessment/result.html.twig', ['result' => $result]));
    }
    private function answerForm(string $id, array $question): \Symfony\Component\Form\FormInterface
    {
        return $this->createForm(AnswerType::class, null, ['question' => $question, 'csrf_token_id' => 'assessment_answer_'.$id.'_'.$question['key'],
            'action' => $this->generateUrl('assessment_answer', ['id' => $id, 'key' => $question['key']])]);
    }
    private function csrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $request->request->getString('_token'))) { throw $this->createAccessDeniedException('Обновите страницу и повторите действие.'); }
    }
    private function privateResponse(Response $response): Response { $response->headers->set('Cache-Control', 'private, no-store'); return $response; }
}
