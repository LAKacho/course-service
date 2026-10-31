<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
#[ORM\Entity]
class Sprint extends LearningItem
{
    #[ORM\ManyToOne(targetEntity: Course::class), ORM\JoinColumn(nullable: false)]
    public Course $course;
    #[ORM\Column]
    #[Assert\PositiveOrZero]
    public int $position = 0;
}
