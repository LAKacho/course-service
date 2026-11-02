<?php
declare(strict_types=1);
namespace App\Access\Infrastructure\Persistence;

use App\Access\Application\Port\AccessRepository;
use App\Learning\Domain\Entity\Card;
use App\Learning\Domain\Entity\Course;
use App\Mentor\Domain\Entity\Enrollment;
use App\Learning\Domain\Entity\LearningPath;
use App\Learning\Domain\Entity\PathCourse;
use App\Access\Domain\Entity\PermissionGrant;
use App\Learning\Domain\Entity\Sprint;
use App\Learning\Domain\Entity\Task;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineAccessRepository implements AccessRepository
{
    public function __construct(private EntityManagerInterface $em) {}
    public function courseBelongsToPath(int $pathId, Course $course): bool
    { return (bool)$this->em->getRepository(PathCourse::class)->count(['path'=>$pathId,'course'=>$course]); }
    public function coursesInPath(LearningPath $path): array
    { return $this->em->getRepository(PathCourse::class)->findBy(['path'=>$path]); }
    public function allCourses(): array
    { return $this->em->getRepository(Course::class)->findAll(); }
    public function hasEnrollment(string $userId, Course $course): bool
    { return (bool)$this->em->getRepository(Enrollment::class)->count(['userId'=>$userId,'course'=>$course]); }
    public function hasPathEnrollment(string $userId, LearningPath $path): bool
    { return (bool)$this->em->getRepository(Enrollment::class)->count(['userId'=>$userId,'learningPath'=>$path]); }
    public function pathsContainingCourse(Course $course): array
    { return array_map(static fn(PathCourse $link): LearningPath=>$link->path,$this->em->getRepository(PathCourse::class)->findBy(['course'=>$course])); }
    public function tasksInContainer(object $container): array
    {
        $qb=$this->em->createQueryBuilder()->select('t')->from(Task::class,'t')->join('t.card','card');
        if($container instanceof Card)$qb->where('t.card = :resource');
        elseif($container instanceof Sprint)$qb->where('card.sprint = :resource');
        else return [];
        return $qb->setParameter('resource',$container)->getQuery()->getResult();
    }
    public function grantExists(string $userId,string $permission,string $scopeKey): bool
    { return (bool)$this->em->getRepository(PermissionGrant::class)->count(['userId'=>$userId,'permission'=>$permission,'scopeKey'=>$scopeKey]); }
    public function saveGrant(PermissionGrant $grant): void { $this->em->persist($grant); $this->em->flush(); }
    public function removeGrant(PermissionGrant $grant): void { $this->em->remove($grant); $this->em->flush(); }
}
