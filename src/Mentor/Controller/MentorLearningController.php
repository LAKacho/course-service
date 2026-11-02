<?php
namespace App\Mentor\Controller;

use App\Mentor\Domain\Entity\Enrollment;

use App\Mentor\Domain\Entity\QuestPrerequisite;
use App\Mentor\Application\Service\{EnrollmentService, QuestPrerequisiteService};
use App\Mentor\Infrastructure\Form\{EnrollmentType, QuestPrerequisiteType};
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

class MentorLearningController extends AbstractController
{
    #[Route('/mentor/learning', name: 'mentor_learning', methods: ['GET'])]
    public function index(EntityManagerInterface $em, \App\Access\Application\Service\AccessChecker $access, \App\Auth\Infrastructure\Session\CurrentUser $user): Response
    {
        return $this->render('mentor/index.html.twig', [
            'enrollments' => array_filter($em->getRepository(Enrollment::class)->findBy([], ['id' => 'ASC']),fn($row)=>$access->can($user->id(),'course.edit',$row->course ?? $row->learningPath)),
            'conditions' => array_filter($em->getRepository(QuestPrerequisite::class)->findBy([], ['id' => 'ASC']),fn($row)=>$access->can($user->id(),'course.edit',$row->task) && $access->can($user->id(),'course.edit',$row->requiredTask)),
        ]);
    }

    #[Route('/mentor/enrollments/new', name: 'mentor_enrollment_new', methods: ['GET', 'POST'])]
    public function enroll(Request $request, EnrollmentService $service): Response
    {
        $entity = new Enrollment();
        $form = $this->createForm(EnrollmentType::class, $entity)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $service->save($entity);
                $this->addFlash('success', 'Программа назначена; ученику выданы права на просмотр и выполнение заданий.');
                return $this->redirectToRoute('mentor_learning', status: 303);
            } catch (\DomainException|UniqueConstraintViolationException) {
                $form->addError(new FormError('Назначение уже существует или цель назначения некорректна. Обновите форму.'));
            }
        }
        return $this->render('mentor/form.html.twig', ['form' => $form, 'heading' => 'Назначить курс ученику', 'help' => 'Укажите ID ученика и выберите конкретный курс. Если нужно назначить все курсы направления, выберите направление вместо курса. Условия открытия настраиваются отдельно.'], new Response(status: $form->isSubmitted() ? 422 : 200));
    }

    #[Route('/mentor/prerequisites/new', name: 'mentor_prerequisite_new', methods: ['GET', 'POST'])]
    public function prerequisite(Request $request, QuestPrerequisiteService $service): Response
    {
        return $this->conditionForm(new QuestPrerequisite(), $request, $service);
    }

    #[Route('/mentor/prerequisites/{id}/edit', name: 'mentor_prerequisite_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(QuestPrerequisite $condition, Request $request, QuestPrerequisiteService $service): Response
    {
        return $this->conditionForm($condition, $request, $service);
    }

    private function conditionForm(QuestPrerequisite $condition, Request $request, QuestPrerequisiteService $service): Response
    {
        $form = $this->createForm(QuestPrerequisiteType::class, $condition)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $service->save($condition);
                $this->addFlash('success', 'Условие открытия сохранено.');
                return $this->redirectToRoute('mentor_learning', status: 303);
            } catch (\DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            } catch (UniqueConstraintViolationException) {
                $form->addError(new FormError('У задания уже есть обязательный предшественник. Обновите форму.'));
            }
        }
        return $this->render('mentor/form.html.twig', ['form' => $form, 'heading' => 'Условие открытия квеста', 'help' => 'Задание откроется после выполнения обязательного предшественника. Без условия задание доступно сразу после назначения.'], new Response(status: $form->isSubmitted() ? 422 : 200));
    }
}
