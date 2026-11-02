<?php
declare(strict_types=1);
namespace App\Learning\Infrastructure\Persistence;

use App\Learning\Domain\Entity\Course;

use App\Learning\Domain\Entity\LearningPath;

use App\Learning\Domain\Entity\PathCourse;

use App\Learning\Domain\Entity\Task;

use App\Learning\Domain\Entity\TaskProgress;
use App\Learning\Application\Port\ImportStore;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineImportStore implements ImportStore
{
    public function __construct(private EntityManagerInterface $em) {}
    public function transactionForSource(string $source,callable $operation): mixed
    {
        return $this->em->wrapInTransaction(function() use($source,$operation): mixed {
            if($this->em->getConnection()->getDatabasePlatform() instanceof PostgreSQLPlatform)
                $this->em->getConnection()->executeQuery('SELECT pg_advisory_xact_lock(hashtext(:source))',['source'=>$source]);
            return $operation();
        });
    }
    public function findByImportKey(string $entityClass,string $key): ?object
    { return $this->em->getRepository($entityClass)->findOneBy(['importKey'=>$key]); }
    public function findPath(int $id): ?LearningPath { return $this->em->find(LearningPath::class,$id); }
    public function lockTask(Task $task): void
    {
        if($this->em->getConnection()->getDatabasePlatform() instanceof PostgreSQLPlatform){
            $this->em->lock($task,LockMode::PESSIMISTIC_WRITE);$this->em->refresh($task);
        }
    }
    public function findPathBySlug(string $slug): ?LearningPath
    { return $this->em->getRepository(LearningPath::class)->findOneBy(['slug'=>$slug]); }
    public function taskHasProgress(Task $task): bool
    { return $this->em->getRepository(TaskProgress::class)->findOneBy(['task'=>$task])!==null; }
    public function findPathCourse(LearningPath $path,Course $course): ?PathCourse
    { return $this->em->getRepository(PathCourse::class)->findOneBy(['path'=>$path,'course'=>$course]); }
    public function nextPathCoursePosition(LearningPath $path): int
    { return (int)$this->em->createQuery('SELECT COALESCE(MAX(pc.position), 0) FROM App\\Entity\\PathCourse pc WHERE pc.path = :path')->setParameter('path',$path)->getSingleScalarResult()+1; }
    public function persist(object $entity): void { $this->em->persist($entity); }
}
