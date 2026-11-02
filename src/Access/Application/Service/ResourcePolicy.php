<?php
declare(strict_types=1);
namespace App\Access\Application\Service;
use App\Learning\Domain\Entity\Course;
use App\Learning\Domain\Entity\Task;
use App\Mentor\Domain\Entity\Enrollment;
use App\Learning\Domain\Entity\PathCourse;
use App\Access\Application\Port\AccessRepository;
use App\Shared\Application\Exception\ResourceNotVisible;
use App\Shared\Application\Port\LearnerTaskAvailability;
final class ResourcePolicy
{
    public function __construct(private AccessChecker $access,private LearnerTaskAvailability $availability,private AccessRepository $repository) {}
    public function enrolled(string $user,Course $course): bool
    {
        if($this->repository->hasEnrollment($user,$course)) return true;
        foreach($this->repository->pathsContainingCourse($course) as $path)
            if($this->repository->hasPathEnrollment($user,$path)) return true;
        return false;
    }
    public function courseVisible(string $user,Course $course): bool
    {
        return $this->access->can($user,'course.view',$course) && ($this->access->manages($user,$course) || $this->enrolled($user,$course));
    }
    public function taskVisible(string $user,Task $task,bool $learner=false): bool
    {
        return $this->access->can($user,'task.view',$task)
            && ((!$learner && $this->access->manages($user,$task)) || $this->availability->isAvailable($task,$user));
    }
    public function learner(string $user,Task $task,string $action): void
    {
        if(!$this->access->can($user,$action,$task) || !$this->taskVisible($user,$task,true)) throw new ResourceNotVisible('Задание не найдено.');
    }
    public function canSubmit(string $user,Task $task): bool
    {
        return $this->access->can($user,'task.submit',$task) && $this->taskVisible($user,$task,true);
    }
    public function containerVisible(string $user,object $resource): bool
    {
        $course=$this->access->course($resource);
        if(!$course || !$this->courseVisible($user,$course))return false;
        if($resource instanceof Course || $this->access->manages($user,$resource))return true;
        if(!$resource instanceof \App\Learning\Domain\Entity\Card && !$resource instanceof \App\Learning\Domain\Entity\Sprint)return false;
        foreach($this->repository->tasksInContainer($resource) as $task)if($this->taskVisible($user,$task,true))return true;
        return false;
    }
}
