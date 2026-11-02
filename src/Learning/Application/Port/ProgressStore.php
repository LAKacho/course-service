<?php
declare(strict_types=1);
namespace App\Learning\Application\Port;
use App\Learning\Domain\Entity\Course;
use App\Learning\Domain\Entity\Task;
interface ProgressStore
{
    /** @return array{0:int,1:int} */
    public function courseCounts(Course $course,string $userId): array;
    public function setCompleted(Task $task,string $userId,bool $completed): void;
}
