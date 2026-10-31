<?php
namespace App\Service;

use App\Entity\{LearningPath, PathCourse};
use Doctrine\ORM\EntityManagerInterface;

class LearningPathManager
{
    public function __construct(private EntityManagerInterface $em, private \App\Access\AccessChecker $access, private CurrentUser $user) {}

    public function save(LearningPath|PathCourse $item): void
    {
        if ($item instanceof PathCourse) {
            $this->access->require($this->user->id(),'course.edit',$item->path);
            $this->access->require($this->user->id(),'course.edit',$item->course);
            $item->path->touch();
        }
        $this->em->persist($item);
        $this->em->flush();
    }
}
