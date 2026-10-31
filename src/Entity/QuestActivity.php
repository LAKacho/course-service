<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Only records starting work. Completion remains exclusively in TaskProgress. */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'unique_quest_activity', columns: ['user_id', 'task_id'])]
class QuestActivity
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 100)]
    public string $userId;

    #[ORM\ManyToOne(targetEntity: Task::class), ORM\JoinColumn(nullable: false)]
    public Task $task;

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $startedAt;

    public function __construct(string $userId, Task $task)
    {
        $this->userId = $userId; $this->task = $task; $this->startedAt = new \DateTimeImmutable();
    }
}
