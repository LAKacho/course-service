<?php
namespace App\Learning\Domain\Entity;


class PathCourse
{
    public ?int $id = null;

    // No cascade remove: a link never owns the lifetime of its course.
    public LearningPath $path;

    public ?Course $course = null;

    public int $position = 0;

    public function __construct(LearningPath $path)
    {
        $this->path = $path;
    }
}
