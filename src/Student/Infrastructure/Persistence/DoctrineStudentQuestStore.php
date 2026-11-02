<?php
declare(strict_types=1);
namespace App\Student\Infrastructure\Persistence;

use App\Student\Domain\Entity\QuestActivity;

use App\Learning\Domain\Entity\Task;
use App\Shared\Infrastructure\Persistence\QuestGraphLock;
use App\Student\Application\Port\StudentQuestStore;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineStudentQuestStore implements StudentQuestStore
{
    public function __construct(private EntityManagerInterface $em) {}
    public function withTransitionLock(Task $task,callable $operation): void
    {
        $this->em->wrapInTransaction(function() use($task,$operation): void {
            if($this->em->getConnection()->getDatabasePlatform() instanceof PostgreSQLPlatform)
                $this->em->getConnection()->executeQuery('SELECT pg_advisory_xact_lock_shared(:key)',['key'=>QuestGraphLock::KEY]);
            $this->em->lock($task,LockMode::PESSIMISTIC_WRITE);
            $operation();
        });
    }
    public function hasStarted(Task $task,string $userId): bool
    { return $this->em->getRepository(QuestActivity::class)->findOneBy(['task'=>$task,'userId'=>$userId])!==null; }
    public function recordStart(Task $task,string $userId): void
    { $this->em->persist(new QuestActivity($userId,$task)); }
}
