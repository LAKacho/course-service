<?php
namespace App\Learning\Domain\Entity;
class Course extends LearningItem
{
    public const STATUSES = ['Черновик' => 'draft', 'Опубликован' => 'published', 'В архиве' => 'archived'];
    public string $status = 'draft';
    public \DateTimeImmutable $createdAt;
    public \DateTimeImmutable $updatedAt;
    public function __construct() { $this->createdAt = $this->updatedAt = new \DateTimeImmutable(); }
    public function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }
}
