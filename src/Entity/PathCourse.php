<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'unique_path_course', columns: ['path_id', 'course_id'])]
#[UniqueEntity(fields: ['path', 'course'], errorPath: 'course', message: 'Этот курс уже добавлен в направление.')]
class PathCourse
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    // No cascade remove: a link never owns the lifetime of its course.
    #[ORM\ManyToOne(targetEntity: LearningPath::class), ORM\JoinColumn(nullable: false)]
    public LearningPath $path;

    #[ORM\ManyToOne(targetEntity: Course::class), ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Выберите существующий курс.')]
    public ?Course $course = null;

    #[ORM\Column]
    #[Assert\PositiveOrZero, Assert\LessThanOrEqual(2147483647)]
    public int $position = 0;

    public function __construct(LearningPath $path)
    {
        $this->path = $path;
    }
}
