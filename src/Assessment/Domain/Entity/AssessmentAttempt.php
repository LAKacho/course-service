<?php
namespace App\Assessment\Domain\Entity;

class AssessmentAttempt
{
public ?int $id = null;
public string $publicId;
    // Portable uniqueness guard: released on completion, unlike a partial index on status.
public ?string $activeKey = null;
public Assessment $assessment;
public string $userId = '';
public string $status = 'in_progress';
    /** Immutable server-only snapshot: contains scoring keys. Never send it to clients. */
public array $snapshot = [];
public \DateTimeImmutable $startedAt;
public ?\DateTimeImmutable $completedAt = null;
    public function __construct() { $this->publicId = bin2hex(random_bytes(16)); $this->startedAt = new \DateTimeImmutable(); }
}
