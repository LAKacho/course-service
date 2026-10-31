<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
#[ORM\MappedSuperclass]
abstract class LearningItem
{
    // Stable source:externalId identity; legacy/manual records remain unbound (NULL).
    #[ORM\Column(length: 201, nullable: true, unique: true)]
    public ?string $importKey = null;
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(normalizer: 'trim'), Assert\Length(max: 180)]
    public string $title = '';
    #[ORM\Column(type: 'text')]
    #[Assert\Length(max: 20000)]
    public string $description = '';
}
