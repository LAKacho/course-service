<?php
namespace App\Mentor\Domain\Entity;
use App\Learning\Domain\Entity\{Course, LearningPath};


class Enrollment
{
    public ?int $id = null;

    public string $userId = '001';

    public ?LearningPath $learningPath = null;

    public ?Course $course = null;

    public \DateTimeImmutable $createdAt;

    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }

    public function hasExactlyOneTarget(): bool
    {
        return ($this->learningPath === null) !== ($this->course === null);
    }
}
