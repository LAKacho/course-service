<?php
declare(strict_types=1);
namespace App\Student\Application\Port;
use App\Learning\Domain\Entity\Task;
interface StudentQuestStore
{
    /** Executes the operation in a transaction while holding the task and dependency-graph locks. */
    public function withTransitionLock(Task $task,callable $operation): void;
    public function hasStarted(Task $task,string $userId): bool;
    public function recordStart(Task $task,string $userId): void;
}
