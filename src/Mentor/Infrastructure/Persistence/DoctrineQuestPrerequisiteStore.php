<?php
declare(strict_types=1);
namespace App\Mentor\Infrastructure\Persistence;
use App\Mentor\Domain\Entity\QuestPrerequisite;
use App\Mentor\Application\Port\QuestPrerequisiteStore;
use App\Shared\Infrastructure\Persistence\QuestGraphLock;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
final class DoctrineQuestPrerequisiteStore implements QuestPrerequisiteStore
{
    public function __construct(private EntityManagerInterface $em) {}
    public function withGraphWriteLock(callable $operation): mixed
    {
        return $this->em->wrapInTransaction(function() use($operation): mixed {
            if($this->em->getConnection()->getDatabasePlatform() instanceof PostgreSQLPlatform)
                $this->em->getConnection()->executeQuery('SELECT pg_advisory_xact_lock(:key)',['key'=>QuestGraphLock::KEY]);
            return $operation();
        });
    }
    public function edges(): array
    { return $this->em->getConnection()->fetchAllAssociative('SELECT id, task_id, required_task_id FROM quest_prerequisite'); }
    public function save(QuestPrerequisite $condition): void { $this->em->persist($condition); }
}
