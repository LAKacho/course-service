<?php
namespace App\Assessment\Entity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity, ORM\Table(name: 'entry_skill_assessment')]
#[ORM\UniqueConstraint(name: 'unique_entry_skill_result', columns: ['result_id', 'skill_code'])]
class SkillAssessment
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] public ?int $id = null;
    #[ORM\ManyToOne(targetEntity: AssessmentResult::class), ORM\JoinColumn(nullable: false)] public AssessmentResult $result;
    #[ORM\ManyToOne(targetEntity: Skill::class), ORM\JoinColumn(nullable: true)] public ?Skill $skill = null;
    #[ORM\Column(length: 50)] public string $skillCode = '';
    #[ORM\Column(length: 120)] public string $skillTitle = '';
    #[ORM\Column] public int $questionCount = 0;
    #[ORM\Column] public int $correctCount = 0;
    #[ORM\Column] public float $weightedPercent = 0;
    #[ORM\Column(length: 20)] public string $confidence = 'low';
    #[ORM\Column(type: 'text')] public string $recommendation = '';
}
