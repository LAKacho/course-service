<?php
namespace App\Assessment\Entity;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\{ArrayCollection, Collection};

#[ORM\Entity, ORM\Table(name: 'entry_question')]
class Question
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] public ?int $id = null;
    #[ORM\Column(length: 60, unique: true)] public string $code = '';
    #[ORM\Column] public int $version = 1;
    #[ORM\Column(length: 30)] public string $type = 'single_choice';
    #[ORM\Column(type: 'text')] public string $text = '';
    #[ORM\Column(type: 'text', nullable: true)] public ?string $codeSnippet = null;
    #[ORM\Column(length: 15, nullable: true)] public ?string $language = null;
    #[ORM\Column(length: 20)] public string $difficulty = 'beginner';
    #[ORM\Column] public int $weight = 1;
    #[ORM\Column] public int $position = 0;
    /** Private bank key: never serialize this entity to a student. */
    #[ORM\Column(type: 'json')] public array $correctAnswer = [];
    #[ORM\Column(type: 'text')] public string $explanation = '';
    #[ORM\Column(length: 64)] public string $sourceHash = '';
    #[ORM\ManyToMany(targetEntity: Skill::class)]
    #[ORM\JoinTable(name: 'entry_question_skill')]
    public Collection $skills;
    public function __construct() { $this->skills = new ArrayCollection(); }
}
