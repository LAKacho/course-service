<?php
declare(strict_types=1);
namespace App\Coding\Domain\Entity;

/** A learner's immutable source submission and its server-verified result state. */
class CodeSubmission
{
    public ?int $id = null;
    public ExerciseVersion $exercise;
    public string $publicId;
    public string $userId = '';
    public string $language = '';
    public string $sourceCode = '';
    public string $status = 'draft';
    public string $mode = 'draft';
    public ?string $runnerJobId = null;
    public ?string $resultHash = null;
    public ?float $score = null;
    /** Allowlisted public case IDs and booleans only, never stdout or runner messages. */
    public array $publicResults = [];
    public \DateTimeImmutable $createdAt;
    public ?\DateTimeImmutable $checkedAt = null;
    public function __construct() { $this->publicId=bin2hex(random_bytes(16));$this->createdAt=new \DateTimeImmutable(); }
}
