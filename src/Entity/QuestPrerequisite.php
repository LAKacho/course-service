<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'unique_quest_predecessor', columns: ['task_id'])]
#[UniqueEntity(fields: ['task'], message: 'У задания уже есть обязательный предшественник. Измените существующее условие.')]
class QuestPrerequisite
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Task::class), ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    public ?Task $task = null;

    #[ORM\ManyToOne(targetEntity: Task::class), ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    public ?Task $requiredTask = null;
}
