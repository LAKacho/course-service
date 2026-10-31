<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
#[ORM\Entity]
#[ORM\Table(name: 'learning_task')]
class Task extends LearningItem
{
    public const TYPES = ['Чтение' => 'reading', 'Видео' => 'video', 'Практика' => 'practice', 'Вопрос' => 'question', 'Код' => 'coding'];
    #[ORM\ManyToOne(targetEntity: Card::class), ORM\JoinColumn(nullable: false)]
    public Card $card;
    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['reading', 'video', 'practice', 'question', 'coding'])]
    public string $type = 'reading';
    #[ORM\Column(length: 2048, nullable: true)]
    #[Assert\Url(protocols: ['http', 'https']), Assert\Length(max: 2048)]
    public ?string $contentUrl = null;
    #[ORM\Column]
    #[Assert\PositiveOrZero]
    public int $position = 0;
}
