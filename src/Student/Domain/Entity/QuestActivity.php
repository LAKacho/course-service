<?php
namespace App\Student\Domain\Entity;
use App\Learning\Domain\Entity\Task;


/** Only records starting work. Completion remains exclusively in TaskProgress. */
class QuestActivity
{
    public ?int $id = null;

    public string $userId;

    public Task $task;

    public \DateTimeImmutable $startedAt;

    public function __construct(string $userId, Task $task)
    {
        $this->userId = $userId; $this->task = $task; $this->startedAt = new \DateTimeImmutable();
    }
}
