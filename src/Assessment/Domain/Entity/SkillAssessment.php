<?php
namespace App\Assessment\Domain\Entity;

class SkillAssessment
{
public ?int $id = null;
public AssessmentResult $result;
public ?Skill $skill = null;
public string $skillCode = '';
public string $skillTitle = '';
public int $questionCount = 0;
public int $correctCount = 0;
public float $weightedPercent = 0;
public string $confidence = 'low';
public string $recommendation = '';
}
