<?php
declare(strict_types=1);
namespace App\Access;
use App\Entity\{Course,Sprint,Card,Task,LearningPath,PathCourse};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException,NotFoundHttpException};

final class AccessChecker
{
    public function __construct(private GrantSource $source, private EntityManagerInterface $em) {}
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
                if ($course?->id && $this->em->getRepository(PathCourse::class)->count(['path'=>$grant->resourceId,'course'=>$course])) return true;
            }
        }
        return false;
    }
    public function require(string $userId,string $permission,?object $resource=null): void
    {
        if (!$this->can($userId,$permission,$resource)) {
            if ($resource) throw new NotFoundHttpException('Ресурс не найден.');
            throw new AccessDeniedHttpException('Недостаточно разрешений.');
        }
    }
    public function manages(string $userId,object $resource): bool
    {
        return $this->can($userId,'course.edit',$resource) || $this->can($userId,'task.review',$resource);
    }
    public function pathVisible(string $userId,LearningPath $path): bool
    {
        if ($this->can($userId,'course.view',$path)) return true;
        foreach($this->em->getRepository(PathCourse::class)->findBy(['path'=>$path]) as $link) if($this->can($userId,'course.view',$link->course)) return true;
        return false;
    }
    public function any(string $userId,string $permission): bool
    {
        if($this->can($userId,$permission))return true;
        foreach($this->em->getRepository(Course::class)->findAll() as $course)if($this->can($userId,$permission,$course))return true;
        return false;
    }
}
