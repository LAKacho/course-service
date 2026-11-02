<?php
namespace App\Assessment\Domain\Entity;

class AssessmentResult
{
public ?int $id = null;
public AssessmentAttempt $attempt;
public string $methodVersion = 'entry-v1';
public int $questionCount = 0;
public int $correctCount = 0;
public float $percentCorrect = 0;
public float $weightedPercent = 0;
public string $preliminaryLevel = 'beginner';
public string $confidence = 'low';
public array $difficultyBreakdown = [];
public \DateTimeImmutable $createdAt;
    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }
}
