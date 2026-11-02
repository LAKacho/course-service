<?php
declare(strict_types=1);
namespace App\Coding\Controller;
use App\Coding\Application\Service\{SubmissionService, CodingView, ExerciseService};
use App\Coding\Infrastructure\Form\{CodeSolutionType, ExerciseType};
use App\Learning\Domain\Entity\Task;
use App\Auth\Infrastructure\Session\CurrentUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

final class CodingController extends AbstractController
{
    public function __construct(private SubmissionService $solutions, private CodingView $view, private CurrentUser $user,private \App\Access\Application\Service\AccessChecker $access) {}
    #[Route('/learn/coding/{id}', name: 'coding_task', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function task(Task $task, Request $request): Response
    {
        $exercise = $this->solutions->exercise($task, $this->user->id());
        $model = $this->view->exercise($exercise); $model['canSubmit']=$this->access->can($this->user->id(),'task.submit',$task);
        $history = $this->view->history($exercise, $this->user->id());
        $selected = null;
        if ($request->query->has('solution')) {
            $row = $this->solutions->owned($request->query->getString('solution'), $this->user->id());
            if ($row->exercise->task->id !== $task->id) { throw $this->createNotFoundException(); }
            $selected = $this->view->submission($row);
        }
        $language = array_key_first($exercise->starters);
        $source = $exercise->starters[$language];
        if ($selected && isset($exercise->starters[$selected['language']])) { $language = $selected['language']; $source = $selected['sourceCode']; }
        $form = $this->createForm(CodeSolutionType::class, ['language' => $language, 'sourceCode' => $source, 'versionId' => $exercise->id],
            ['languages' => array_keys($exercise->starters), 'csrf_token_id' => 'coding_'.$task->id]);
        $form->handleRequest($request); $error = null; $status = 200;
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $data = $form->getData();
                try {
                    $saved = $this->solutions->save($task, $this->user->id(), (int) $data['versionId'], $data['language'], $data['sourceCode'], $request->request->getString('action', 'draft'));
                    $this->addFlash('notice', $saved->status === 'draft' && $saved->mode !== 'draft' ? 'Код сохранён. Проверка временно недоступна.' : 'Решение сохранено.');
                    return $this->redirectToRoute('coding_task', ['id' => $task->id, 'solution' => $saved->publicId], 303);
                } catch (\InvalidArgumentException $exception) { $error = $exception->getMessage(); $status = 422; }
            } else { $status = 422; }
        }
        return $this->privateResponse($this->render('coding/task.html.twig', ['exercise' => $model, 'history' => $history, 'selected' => $selected,
            'form' => $form, 'runnerAvailable' => $this->solutions->available(), 'error' => $error], new Response(status: $status)));
    }
    #[Route('/learn/coding/solutions/{id}/refresh', name: 'coding_refresh', requirements: ['id' => '[a-f0-9]{32}'], methods: ['POST'])]
    public function refresh(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('coding_refresh_'.$id, $request->request->getString('_token'))) { throw $this->createAccessDeniedException(); }
        $submission = $this->solutions->owned($id, $this->user->id());
        $this->solutions->exercise($submission->exercise->task, $this->user->id());
        $this->solutions->refresh($submission, $this->user->id());
        return $this->redirectToRoute('coding_task', ['id' => $submission->exercise->task->id, 'solution' => $id], 303);
    }
    #[Route('/mentor/coding/{id}/versions/new', name: 'coding_version_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function create(Task $task, Request $request, ExerciseService $exercises): Response
    {
        $form = $this->createForm(ExerciseType::class, ['title' => $task->title, 'statement' => $task->description,
            'publicTests' => '[{"id":"example","input":[2,3],"expected":5}]', 'hiddenTests' => '[{"id":"boundary","input":[0,0],"expected":0}]']);
        $form->handleRequest($request); $error = null; $status = 200;
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $data = $form->getData();
                try {
                    $starters = array_filter(['php' => $data['php'] ?? '', 'go' => $data['go'] ?? ''], static fn($code) => trim($code) !== '');
                    $public = json_decode($data['publicTests'], true, 32, JSON_THROW_ON_ERROR);
                    $hidden = json_decode($data['hiddenTests'], true, 32, JSON_THROW_ON_ERROR);
                    if (!is_array($public) || !is_array($hidden)) { throw new \InvalidArgumentException('Тесты должны быть JSON-массивами.'); }
                    $exercises->createVersion($task, $data['title'], $data['statement'], $starters, $public, $hidden);
                    $this->addFlash('notice', 'Создана новая версия практического задания.');
                    return $this->redirectToRoute('card', ['id' => $task->card->id], 303);
                } catch (\JsonException|\InvalidArgumentException|\DomainException $exception) { $error = $exception->getMessage(); $status = 422; }
            } else { $status = 422; }
        }
        return $this->privateResponse($this->render('coding/create.html.twig', ['form' => $form, 'error' => $error, 'task' => $task], new Response(status: $status)));
    }
    private function privateResponse(Response $response): Response { $response->headers->set('Cache-Control', 'private, no-store'); return $response; }
}
