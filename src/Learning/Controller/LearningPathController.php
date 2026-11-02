<?php
namespace App\Learning\Controller;

use App\Learning\Domain\Entity\LearningPath;

use App\Learning\Domain\Entity\PathCourse;
use App\Learning\Infrastructure\Form\{LearningPathType, PathCourseType, PathCoursePositionType};
use App\Learning\Infrastructure\Persistence\LearningPathRepository;
use App\Learning\Application\Service\LearningPathManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Form\{FormFactoryInterface, FormInterface};
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/learning-paths', name: 'learning_path_')]
class LearningPathController extends AbstractController
{
    public function __construct(private LearningPathRepository $repository, private LearningPathManager $manager, private FormFactoryInterface $forms) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('learning_path/index.html.twig', ['paths' => $this->repository->all()]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        return $this->editForm(new LearningPath(), $request);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(LearningPath $path, Request $request): Response
    {
        return $this->editForm($path, $request);
    }

    private function editForm(LearningPath $path, Request $request): Response
    {
        $new = $path->id === null;
        $form = $this->createForm(LearningPathType::class, $path)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->save($path);
            $this->addFlash('success', 'Направление сохранено.');
            return $this->redirectToRoute('learning_path_show', ['id' => $path->id], 303);
        }
        return $this->render('learning_path/form.html.twig', ['form' => $form, 'path' => $path, 'new' => $new], new Response(status: $form->isSubmitted() ? 422 : 200));
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(LearningPath $path): Response
    {
        $links = $this->repository->courses($path);
        $positions = [];
        foreach ($links as $link) {
            $positions[$link->id] = $this->positionForm($link, [
                'action' => $this->generateUrl('learning_path_position', ['id' => $path->id, 'linkId' => $link->id]),
            ])->createView();
        }
        return $this->render('learning_path/show.html.twig', ['path' => $path, 'links' => $links, 'positions' => $positions]);
    }

    #[Route('/{id}/courses/add', name: 'add_course', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function addCourse(LearningPath $path, Request $request): Response
    {
        $link = new PathCourse($path);
        $form = $this->createForm(PathCourseType::class, $link)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->save($link);
            $this->addFlash('success', 'Курс добавлен в направление.');
            return $this->redirectToRoute('learning_path_show', ['id' => $path->id], 303);
        }
        return $this->render('learning_path/add_course.html.twig', ['path' => $path, 'form' => $form], new Response(status: $form->isSubmitted() ? 422 : 200));
    }

    #[Route('/{id}/courses/{linkId}/position', name: 'position', requirements: ['id' => '\d+', 'linkId' => '\d+'], methods: ['POST'])]
    public function position(LearningPath $path, int $linkId, Request $request): Response
    {
        $link = $this->repository->link($path, $linkId) ?? throw $this->createNotFoundException('Курс не найден в этом направлении.');
        $form = $this->positionForm($link)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->save($link);
            $this->addFlash('success', 'Порядок курса сохранён.');
            return $this->redirectToRoute('learning_path_show', ['id' => $path->id], 303);
        }
        return $this->render('learning_path/position.html.twig', ['path' => $path, 'link' => $link, 'form' => $form], new Response(status: 422));
    }

    private function positionForm(PathCourse $link, array $options = []): FormInterface
    {
        return $this->forms->createNamed('path_course_position_'.$link->id, PathCoursePositionType::class, $link, $options + ['csrf_token_id' => 'path_position_'.$link->id]);
    }

    private function save(LearningPath|PathCourse $item): void
    {
        try {
            $this->manager->save($item);
        } catch (UniqueConstraintViolationException $exception) {
            // Handles a concurrent insert after successful form validation.
            throw new ConflictHttpException('Запись уже существует. Обновите страницу.', $exception);
        }
    }
}
