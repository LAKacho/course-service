<?php
declare(strict_types=1);
namespace App\Student\Domain;

enum QuestState: string
{
    case Locked = 'locked';
    case Available = 'available';
    case InProgress = 'in_progress';
    case Completed = 'completed';
}
