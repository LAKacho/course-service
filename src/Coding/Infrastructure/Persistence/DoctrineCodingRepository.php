<?php
declare(strict_types=1);
namespace App\Coding\Infrastructure\Persistence;
use App\Coding\Application\Port\CodingRepository;
use App\Coding\Domain\Entity\{CodeSubmission,ExerciseVersion};
use App\Learning\Domain\Entity\Task;
use App\Learning\Domain\Entity\TaskProgress;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
final class DoctrineCodingRepository implements CodingRepository
{
    public function __construct(private EntityManagerInterface $em) {}
    public function transaction(callable $operation): mixed { return $this->em->wrapInTransaction($operation); }
    public function lock(object $entity): void { $this->em->lock($entity,LockMode::PESSIMISTIC_WRITE); }
    public function refresh(object $entity): void { $this->em->refresh($entity); }
    public function latestExercise(Task $task): ?ExerciseVersion
    { return $this->em->getRepository(ExerciseVersion::class)->findOneBy(['task'=>$task],['version'=>'DESC']); }
    public function taskHasProgress(Task $task): bool
    { return (bool)$this->em->getRepository(TaskProgress::class)->count(['task'=>$task]); }
    public function ownedSubmission(string $publicId,string $userId): ?CodeSubmission
    { return $this->em->getRepository(CodeSubmission::class)->findOneBy(['publicId'=>$publicId,'userId'=>$userId]); }
    public function history(Task $task,string $userId): array
    { return $this->em->createQuery('SELECT s FROM App\\Coding\\Domain\\Entity\\CodeSubmission s JOIN s.exercise e WHERE e.task = :task AND s.userId = :user ORDER BY s.id DESC')->setParameter('task',$task)->setParameter('user',$userId)->setMaxResults(30)->getResult(); }
    public function hasProgress(Task $task,string $userId): bool
    { return $this->em->getRepository(TaskProgress::class)->findOneBy(['task'=>$task,'userId'=>$userId])!==null; }
    public function persist(object $entity): void { $this->em->persist($entity); }
    public function save(object $entity): void { $this->em->persist($entity);$this->em->flush(); }
    public function flush(): void { $this->em->flush(); }
}
