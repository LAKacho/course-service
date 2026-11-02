<?php
declare(strict_types=1);
namespace App\Access\Application\Service;
use App\Access\Application\Port\GrantSource;
use App\Access\Application\Exception\PermissionDenied;
use App\Shared\Application\Exception\ResourceNotVisible;
use App\Access\Domain\PermissionCatalog;
use App\Learning\Domain\Entity\Course;
use App\Learning\Domain\Entity\Sprint;
use App\Learning\Domain\Entity\Card;
use App\Learning\Domain\Entity\Task;
use App\Learning\Domain\Entity\LearningPath;
use App\Learning\Domain\Entity\PathCourse;
use App\Access\Application\Port\AccessRepository;

final class AccessChecker
{
    public function __construct(private GrantSource $source, private AccessRepository $repository) {}
    public function course(object $resource): ?Course
    {
        return match(true) { $resource instanceof Course=>$resource, $resource instanceof Sprint=>$resource->course,
            $resource instanceof Card=>$resource->sprint->course,$resource instanceof Task=>$resource->card->sprint->course,default=>null };
    }
    public function can(string $userId, string $permission, ?object $resource = null): bool
    {
        if ($userId === '' || !isset(PermissionCatalog::ALL[$permission])) return false;
        $course = $resource ? $this->course($resource) : null;
        foreach ($this->source->forUser($userId) as $grant) {
            if ($grant->permission !== $permission || !PermissionCatalog::validScope($permission,$grant->scope)) continue;
            if ($grant->scope === 'global' && $grant->resourceId===null) return true;
            if ($grant->scope === 'course' && $course?->id && $grant->resourceId === $course->id) return true;
            if ($grant->scope === 'learning_path' && $grant->resourceId) {
                if ($resource instanceof LearningPath && $grant->resourceId === $resource->id) return true;
                // Explicit one-way membership inheritance; no traversal through a shared course into another path.
                if ($course?->id && $this->repository->courseBelongsToPath($grant->resourceId,$course)) return true;
            }
        }
        return false;
    }
    public function require(string $userId,string $permission,?object $resource=null): void
    {
        if (!$this->can($userId,$permission,$resource)) {
            if ($resource) throw new ResourceNotVisible('Ресурс не найден.');
            throw new PermissionDenied('Недостаточно разрешений.');
        }
    }
    public function manages(string $userId,object $resource): bool
    {
        return $this->can($userId,'course.edit',$resource) || $this->can($userId,'task.review',$resource);
    }
    public function pathVisible(string $userId,LearningPath $path): bool
    {
        if ($this->can($userId,'course.view',$path)) return true;
        foreach($this->repository->coursesInPath($path) as $link) if($this->can($userId,'course.view',$link->course)) return true;
        return false;
    }
    public function any(string $userId,string $permission): bool
    {
        if($this->can($userId,$permission))return true;
        foreach($this->repository->allCourses() as $course)if($this->can($userId,$permission,$course))return true;
        return false;
    }
}
