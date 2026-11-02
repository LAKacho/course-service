<?php
namespace App\Learning\Domain\Entity;


class LearningPath extends LearningItem
{
    public const STATUSES = ['Черновик' => 'draft', 'Опубликовано' => 'published', 'В архиве' => 'archived'];

    public string $slug = '';

    public string $status = 'draft';

    public \DateTimeImmutable $createdAt;

    public \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
