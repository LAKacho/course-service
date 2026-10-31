<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
#[ORM\Entity]
class Card extends LearningItem
{
    public const STATUSES = ['К выполнению' => 'todo', 'В работе' => 'in_progress', 'Готово' => 'done'];
    #[ORM\ManyToOne(targetEntity: Sprint::class), ORM\JoinColumn(nullable: false)]
    public Sprint $sprint;
    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['todo', 'in_progress', 'done'])]
    public string $status = 'todo';
    #[ORM\Column]
    #[Assert\PositiveOrZero]
    public int $position = 0;
}
