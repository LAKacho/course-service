<?php
namespace App\Learning\Application\Service;

use App\Learning\Domain\Entity\LearningPath;

use App\Learning\Domain\Entity\PathCourse;
use App\Shared\Application\Port\CurrentUserId;
use App\Learning\Application\Port\LearningWriteRepository;

class LearningPathManager
{
    public function __construct(private LearningWriteRepository $repository, private \App\Access\Application\Service\AccessChecker $access, private CurrentUserId $user) {}

    public function save(LearningPath|PathCourse $item): void
    {
        if ($item instanceof PathCourse) {
            $this->access->require($this->user->id(),'course.edit',$item->path);
            $this->access->require($this->user->id(),'course.edit',$item->course);
            $item->path->touch();
        }
        $this->repository->save($item);
    }
}
