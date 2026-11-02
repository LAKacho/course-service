<?php
namespace App\Learning\Domain\Entity;
class TaskProgress
{
    public ?int $id = null;
    public string $userId;
    public Task $task;
    public \DateTimeImmutable $completedAt;
    public function __construct(string $userId, Task $task) {
        $this->userId = $userId; $this->task = $task; $this->completedAt = new \DateTimeImmutable();
    }
}
