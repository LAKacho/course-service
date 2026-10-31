<?php
namespace App\Controller;

use App\Entity\Task;
use App\Service\{CurrentUser, StudentQuestService, StudentPathPresenter};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

class StudentController extends AbstractController
{
    #[Route('/learn', name: 'student_home', methods: ['GET'])]
    public function home(CurrentUser $user, StudentPathPresenter $presenter): Response
    {
        $response = $this->render('student/home.html.twig', $presenter->present($user->id()));
        $response->headers->set('Cache-Control', 'private, no-store');
        return $response;
    }

    #[Route('/learn/quests/{id}/start', name: 'student_start', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function start(Task $task, Request $request, StudentQuestService $learning): Response
    {
        $this->checkToken($request, 'student_start_'.$task->id);
        $learning->start($task);
        return $this->redirectToRoute('student_home', status: 303);
    }

    #[Route('/learn/quests/{id}/complete', name: 'student_complete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function complete(Task $task, Request $request, StudentQuestService $learning): Response
    {
        $this->checkToken($request, 'student_complete_'.$task->id);
        $learning->complete($task);
        return $this->redirectToRoute('student_home', status: 303);
    }

    private function checkToken(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $request->request->getString('_token'))) { throw $this->createAccessDeniedException('Обновите страницу и повторите действие.'); }
    }
}
