<?php
declare(strict_types=1);
namespace App\Coding\Entity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity, ORM\Table(name: 'coding_submission')]
class CodeSubmission
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] public ?int $id = null;
    #[ORM\Column(length: 32, unique: true)] public string $publicId;
    #[ORM\ManyToOne(targetEntity: ExerciseVersion::class), ORM\JoinColumn(nullable: false)] public ExerciseVersion $exercise;
    #[ORM\Column(length: 100)] public string $userId = '';
    #[ORM\Column(length: 10)] public string $language = '';
    #[ORM\Column(type: 'text')] public string $sourceCode = '';
    #[ORM\Column(length: 20)] public string $status = 'draft';
    #[ORM\Column(length: 10)] public string $mode = 'draft';
    #[ORM\Column(length: 100, nullable: true, unique: true)] public ?string $runnerJobId = null;
    #[ORM\Column(length: 64, nullable: true)] public ?string $resultHash = null;
    #[ORM\Column(type: 'float', nullable: true)] public ?float $score = null;
    /** Allowlisted public case IDs and booleans only, never stdout or runner messages. */
    #[ORM\Column(type: 'json')] public array $publicResults = [];
    #[ORM\Column(type: 'datetime_immutable')] public \DateTimeImmutable $createdAt;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)] public ?\DateTimeImmutable $checkedAt = null;
    public function __construct() { $this->publicId = bin2hex(random_bytes(16)); $this->createdAt = new \DateTimeImmutable(); }
}
