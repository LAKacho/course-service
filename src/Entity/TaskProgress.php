<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'unique_user_task', columns: ['user_id', 'task_id'])]
class TaskProgress
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\Column(length: 100)]
    public string $userId;
    #[ORM\ManyToOne(targetEntity: Task::class), ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Task $task;
    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $completedAt;
    public function __construct(string $userId, Task $task) {
        $this->userId = $userId; $this->task = $task; $this->completedAt = new \DateTimeImmutable();
    }
}
