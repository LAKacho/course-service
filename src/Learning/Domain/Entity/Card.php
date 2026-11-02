<?php
namespace App\Learning\Domain\Entity;
class Card extends LearningItem
{
    public const STATUSES = ['К выполнению' => 'todo', 'В работе' => 'in_progress', 'Готово' => 'done'];
    public Sprint $sprint;
    public string $status = 'todo';
    public int $position = 0;
}
