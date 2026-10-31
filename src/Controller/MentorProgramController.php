<?php
namespace App\Controller;

use App\Entity\LearningPath;
use App\Repository\{LearningPathRepository, MentorProgramRepository};
use App\Service\{CurrentUser, QuestAvailabilityService, StudentPathPresenter};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MentorProgramController extends AbstractController
{
    #[Route('/mentor/programs', name: 'mentor_programs', methods: ['GET'])]
    public function index(LearningPathRepository $repository): Response
    {
        return $this->render('mentor/programs.html.twig', ['paths' => $repository->all()]);
    }

    #[Route('/mentor/programs/{id}', name: 'mentor_program', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(LearningPath $path, MentorProgramRepository $repository, QuestAvailabilityService $availability, CurrentUser $user): Response
    {
        $states = [];
        foreach ($availability->states($user->id()) as $id => $item) { $states[$id] = StudentPathPresenter::STATES[$item['state']->value]; }
        return $this->render('mentor/program.html.twig', ['learningPath' => $path, 'outline' => $repository->outline($path),
            'states' => $states, 'types' => StudentPathPresenter::TYPES]);
    }
}
