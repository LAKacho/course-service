<?php
namespace App\Assessment\Entity;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\{ArrayCollection, Collection};

#[ORM\Entity, ORM\Table(name: 'entry_assessment')]
class Assessment
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] public ?int $id = null;
    #[ORM\Column(length: 40, unique: true)] public string $code = '';
    #[ORM\Column(length: 180)] public string $title = '';
    #[ORM\Column(type: 'text')] public string $description = '';
    #[ORM\Column] public int $version = 1;
    #[ORM\Column(type: 'json')] public array $coreSkills = [];
    #[ORM\ManyToMany(targetEntity: Question::class)]
    #[ORM\JoinTable(name: 'entry_assessment_question')]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    public Collection $questions;
    public function __construct() { $this->questions = new ArrayCollection(); }
}
