<?php
declare(strict_types=1);
namespace App\Learning\Application\Service;

use App\Learning\Domain\Entity\Course;

use App\Learning\Domain\Entity\Task;
use App\Learning\Application\Port\ProgressStore;
use App\Learning\Domain\Service\ProgressCalculator;
use App\Shared\Application\Exception\Conflict;
use App\Shared\Application\Port\CurrentUserId;

final class ProgressService
{
    public function __construct(private ProgressStore $repository,private CurrentUserId $user) {}
    /** @deprecated Use the domain calculator; retained for existing callers. */
    public static function percentage(int $total,int $completed): int { return ProgressCalculator::percentage($total,$completed); }
    public function course(Course $course): array
    {
        [$total,$completed]=$this->repository->courseCounts($course,$this->user->id());
        return ['total'=>$total,'completed'=>$completed,'percent'=>ProgressCalculator::percentage($total,$completed)];
    }
    public function setCompleted(Task $task,bool $completed): void
    {
        if($completed && $task->type==='coding')throw new Conflict('Кодовое задание завершается только подтверждённой финальной проверкой.');
        $this->repository->setCompleted($task,$this->user->id(),$completed);
    }
}
