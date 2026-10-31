<?php
namespace App\Controller;
use App\Entity\{Course, Sprint, Card, Task, LearningItem};
use App\Form\LearningType;
use App\Repository\LearningRepository;
use App\Service\{LearningEditor, ProgressService, CurrentUser, CardWorkflow};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
class LearningController extends AbstractController
{
    private const CLASSES = ['course' => Course::class, 'sprint' => Sprint::class, 'card' => Card::class, 'task' => Task::class];
    public function __construct(private LearningRepository $repository, private ProgressService $progress, private LearningEditor $editor) {}
    #[Route('/', name: 'dashboard', methods: ['GET'])]
    public function dashboard(): Response {
        return $this->render('dashboard.html.twig', ['stats' => $this->repository->statistics(), 'courses' => array_slice($this->repository->courses(), 0, 4)]);
    }
    #[Route('/courses', name: 'courses', methods: ['GET'])]
    public function courses(): Response { return $this->render('courses.html.twig', ['courses' => $this->repository->courses()]); }
    #[Route('/courses/{id}', name: 'course', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function course(Course $course): Response {
        return $this->render('course.html.twig', ['course' => $course, 'sprints' => $this->repository->sprints($course), 'progress' => $this->progress->course($course)]);
    }
    #[Route('/sprints/{id}', name: 'sprint', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function sprint(Sprint $sprint): Response {
        return $this->render('sprint.html.twig', ['sprint' => $sprint, 'cards' => $this->repository->cards($sprint), 'statuses' => Card::STATUSES]);
    }
    #[Route('/cards/{id}', name: 'card', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function card(Card $card, CurrentUser $user): Response {
        return $this->render('card.html.twig', ['card' => $card, 'tasks' => $this->repository->tasks($card), 'completed' => $this->repository->completedIds($card, $user->id()), 'types' => Task::TYPES, 'statuses' => Card::STATUSES]);
    }
    #[Route('/manage/{kind}/new/{parentId}', name: 'item_new', defaults: ['parentId' => 0], requirements: ['kind' => 'course|sprint|card|task', 'parentId' => '\d+'], methods: ['GET', 'POST'])]
    public function create(string $kind, int $parentId, Request $request, EntityManagerInterface $em): Response {
        $item = new (self::CLASSES[$kind])();
        $parent = match ($kind) { 'sprint' => ['course', Course::class], 'card' => ['sprint', Sprint::class], 'task' => ['card', Card::class], default => null };
        if ($parent) { $item->{$parent[0]} = $em->find($parent[1], $parentId) ?? throw $this->createNotFoundException('Родительская запись не найдена.'); }
        return $this->editForm($item, $kind, $request);
    }
    #[Route('/manage/{kind}/{id}/edit', name: 'item_edit', requirements: ['kind' => 'course|sprint|card|task', 'id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(string $kind, int $id, Request $request, EntityManagerInterface $em): Response {
        return $this->editForm($em->find(self::CLASSES[$kind], $id) ?? throw $this->createNotFoundException('Запись не найдена.'), $kind, $request);
    }
    private function editForm(LearningItem $item, string $kind, Request $request): Response {
        $new = $item->id === null;
        $form = $this->createForm(LearningType::class, $item)->handleRequest($request);
        $back = match (true) { $item instanceof Course => $item->id ? $this->generateUrl('course', ['id' => $item->id]) : $this->generateUrl('courses'), $item instanceof Sprint => $this->generateUrl('course', ['id' => $item->course->id]), $item instanceof Card => $this->generateUrl('sprint', ['id' => $item->sprint->id]), $item instanceof Task => $this->generateUrl('card', ['id' => $item->card->id]) };
        if ($form->isSubmitted() && $form->isValid()) {
            $this->editor->save($item);
            $this->addFlash('success', 'Изменения сохранены.');
            return $this->redirect($item instanceof Task ? $back : $this->generateUrl($kind, ['id' => $item->id]), 303);
        }
        return $this->render('form.html.twig', ['form' => $form, 'kind' => $kind, 'new' => $new, 'back' => $back], new Response(status: $form->isSubmitted() ? 422 : 200));
    }
    #[Route('/cards/{id}/status', name: 'card_status', methods: ['POST'])]
    public function status(Card $card, Request $request, CardWorkflow $workflow): Response {
        $this->checkToken($request, 'card_'.$card->id);
        try { $workflow->changeStatus($card, $request->request->getString('status')); }
        catch (\InvalidArgumentException $e) { throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException($e->getMessage()); }
        $this->editor->save($card);
        return $this->redirectToRoute('sprint', ['id' => $card->sprint->id], 303);
    }
    #[Route('/tasks/{id}/completion', name: 'task_completion', methods: ['POST'])]
    public function complete(Task $task, Request $request): Response {
        $this->checkToken($request, 'task_'.$task->id);
        $value = $request->request->getString('completed');
        if (!in_array($value, ['0', '1'], true)) { throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Некорректное значение выполнения.'); }
        $this->progress->setCompleted($task, $value === '1');
        return $this->redirectToRoute('card', ['id' => $task->card->id], 303);
    }
    private function checkToken(Request $request, string $id): void {
        if (!$this->isCsrfTokenValid($id, $request->request->getString('_token'))) { throw $this->createAccessDeniedException('Форма устарела. Обновите страницу и повторите действие.'); }
    }
}
