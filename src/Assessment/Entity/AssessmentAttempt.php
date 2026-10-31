<?php
namespace App\Assessment\Entity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity, ORM\Table(name: 'entry_attempt')]
class AssessmentAttempt
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] public ?int $id = null;
    #[ORM\Column(length: 32, unique: true)] public string $publicId;
    // Portable uniqueness guard: released on completion, unlike a partial index on status.
    #[ORM\Column(length: 64, nullable: true, unique: true)] public ?string $activeKey = null;
    #[ORM\ManyToOne(targetEntity: Assessment::class), ORM\JoinColumn(nullable: false)] public Assessment $assessment;
    #[ORM\Column(length: 100)] public string $userId = '';
    #[ORM\Column(length: 20)] public string $status = 'in_progress';
    /** Immutable server-only snapshot: contains scoring keys. Never send it to clients. */
    #[ORM\Column(type: 'json')] public array $snapshot = [];
    #[ORM\Column(type: 'datetime_immutable')] public \DateTimeImmutable $startedAt;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)] public ?\DateTimeImmutable $completedAt = null;
    public function __construct() { $this->publicId = bin2hex(random_bytes(16)); $this->startedAt = new \DateTimeImmutable(); }
}
