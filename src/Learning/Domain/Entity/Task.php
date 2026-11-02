<?php
namespace App\Learning\Domain\Entity;
class Task extends LearningItem
{
    public const TYPES = ['Чтение' => 'reading', 'Видео' => 'video', 'Практика' => 'practice', 'Вопрос' => 'question', 'Код' => 'coding'];
    public Card $card;
    public string $type = 'reading';
    public ?string $contentUrl = null;
    public int $position = 0;
}
