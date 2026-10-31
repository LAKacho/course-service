<?php
namespace App\Entity;

enum QuestState: string
{
    case Locked = 'locked';
    case Available = 'available';
    case InProgress = 'in_progress';
    case Completed = 'completed';
}
