<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
#[ORM\Entity, ORM\HasLifecycleCallbacks]
class Course extends LearningItem
{
    public const STATUSES = ['Черновик' => 'draft', 'Опубликован' => 'published', 'В архиве' => 'archived'];
    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['draft', 'published', 'archived'])]
    public string $status = 'draft';
    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $createdAt;
    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $updatedAt;
    public function __construct() { $this->createdAt = $this->updatedAt = new \DateTimeImmutable(); }
    #[ORM\PreUpdate]
    public function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }
}
