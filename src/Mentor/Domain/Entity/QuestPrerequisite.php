<?php
namespace App\Mentor\Domain\Entity;
use App\Learning\Domain\Entity\Task;


class QuestPrerequisite
{
    public ?int $id = null;

    public ?Task $task = null;

    public ?Task $requiredTask = null;
}
