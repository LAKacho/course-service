<?php
namespace App\Assessment\Entity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity, ORM\Table(name: 'entry_answer_option')]
#[ORM\UniqueConstraint(name: 'unique_entry_option', columns: ['question_id', 'code'])]
class AnswerOption
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] public ?int $id = null;
    #[ORM\ManyToOne(targetEntity: Question::class), ORM\JoinColumn(nullable: false)] public Question $question;
    #[ORM\Column(length: 20)] public string $code = '';
    #[ORM\Column(type: 'text')] public string $text = '';
    #[ORM\Column] public int $position = 0;
}
