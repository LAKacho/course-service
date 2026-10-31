<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity, ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'unique_learning_path_slug', columns: ['slug'])]
#[UniqueEntity(fields: ['slug'], message: 'Направление с таким адресом уже существует.')]
class LearningPath extends LearningItem
{
    public const STATUSES = ['Черновик' => 'draft', 'Опубликовано' => 'published', 'В архиве' => 'archived'];

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank, Assert\Length(max: 180)]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', message: 'Используйте строчные латинские буквы, цифры и одиночные дефисы между словами.')]
    public string $slug = '';

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['draft', 'published', 'archived'])]
    public string $status = 'draft';

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
