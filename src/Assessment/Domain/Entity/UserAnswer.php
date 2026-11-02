<?php
namespace App\Assessment\Domain\Entity;

class UserAnswer
{
public ?int $id = null;
public AssessmentAttempt $attempt;
public ?Question $question = null;
public string $questionKey = '';
public array $selectedOptions = [];
public \DateTimeImmutable $answeredAt;
    public function __construct() { $this->answeredAt = new \DateTimeImmutable(); }
}
