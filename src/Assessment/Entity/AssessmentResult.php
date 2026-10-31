<?php
namespace App\Assessment\Entity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity, ORM\Table(name: 'entry_result')]
class AssessmentResult
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] public ?int $id = null;
    #[ORM\OneToOne(targetEntity: AssessmentAttempt::class), ORM\JoinColumn(nullable: false)] public AssessmentAttempt $attempt;
    #[ORM\Column(length: 30)] public string $methodVersion = 'entry-v1';
    #[ORM\Column] public int $questionCount = 0;
    #[ORM\Column] public int $correctCount = 0;
    #[ORM\Column] public float $percentCorrect = 0;
    #[ORM\Column] public float $weightedPercent = 0;
    #[ORM\Column(length: 30)] public string $preliminaryLevel = 'beginner';
    #[ORM\Column(length: 20)] public string $confidence = 'low';
    #[ORM\Column(type: 'json')] public array $difficultyBreakdown = [];
    #[ORM\Column(type: 'datetime_immutable')] public \DateTimeImmutable $createdAt;
    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }
}
