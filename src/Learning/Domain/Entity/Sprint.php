<?php
namespace App\Learning\Domain\Entity;
class Sprint extends LearningItem
{
    public Course $course;
    public int $position = 0;
}
