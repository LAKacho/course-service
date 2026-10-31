<?php
namespace App\Service;
use App\Entity\{Course, Task, TaskProgress};
use App\Repository\LearningRepository;
use Doctrine\ORM\EntityManagerInterface;
class ProgressService
{
    public function __construct(private LearningRepository $repository, private CurrentUser $user, private EntityManagerInterface $em) {}
    public static function percentage(int $total, int $completed): int { return $total === 0 ? 0 : (int) round($completed / $total * 100); }
    public function course(Course $course): array {
        [$total, $completed] = $this->repository->courseCounts($course, $this->user->id());
        return ['total' => $total, 'completed' => $completed, 'percent' => self::percentage($total, $completed)];
    }
    public function setCompleted(Task $task, bool $completed): void {
        if ($completed && $task->type === 'coding') {
            throw new \Symfony\Component\HttpKernel\Exception\ConflictHttpException('Кодовое задание завершается только подтверждённой финальной проверкой.');
        }
        // Serialize concurrent changes for this task; the unique constraint is a second guard.
        $this->em->wrapInTransaction(function () use ($task, $completed): void {
            $this->em->lock($task, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
            $progress = $this->repository->completion($task, $this->user->id());
            if ($completed && !$progress) { $this->em->persist(new TaskProgress($this->user->id(), $task)); }
            if (!$completed && $progress) { $this->em->remove($progress); }
        });
    }
}
