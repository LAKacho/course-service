<?php
namespace App\Assessment\Entity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity, ORM\Table(name: 'entry_skill')]
class Skill
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] public ?int $id = null;
    #[ORM\Column(length: 50, unique: true)] public string $code = '';
    #[ORM\Column(length: 120)] public string $title = '';
    #[ORM\Column(type: 'text')] public string $recommendation = '';
}
