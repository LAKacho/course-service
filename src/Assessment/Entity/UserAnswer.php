<?php
namespace App\Assessment\Entity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity, ORM\Table(name: 'entry_user_answer')]
#[ORM\UniqueConstraint(name: 'unique_entry_answer', columns: ['attempt_id', 'question_key'])]
class UserAnswer
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] public ?int $id = null;
    #[ORM\ManyToOne(targetEntity: AssessmentAttempt::class), ORM\JoinColumn(nullable: false)] public AssessmentAttempt $attempt;
    #[ORM\ManyToOne(targetEntity: Question::class), ORM\JoinColumn(nullable: true)] public ?Question $question = null;
    #[ORM\Column(length: 60)] public string $questionKey = '';
    #[ORM\Column(type: 'json')] public array $selectedOptions = [];
    #[ORM\Column(type: 'datetime_immutable')] public \DateTimeImmutable $answeredAt;
    public function __construct() { $this->answeredAt = new \DateTimeImmutable(); }
}
