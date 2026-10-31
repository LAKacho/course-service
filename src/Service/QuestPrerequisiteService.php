<?php
namespace App\Service;

use App\Entity\QuestPrerequisite;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;

class QuestPrerequisiteService
{
    // Shared by prerequisite writes (exclusive) and student transitions (shared).
    public const GRAPH_LOCK = 843014;

    public function __construct(private EntityManagerInterface $em, private \App\Access\AccessChecker $access, private CurrentUser $user) {}

    public function save(QuestPrerequisite $condition): void
    {
        $this->access->require($this->user->id(),'course.edit',$condition->task);
        $this->access->require($this->user->id(),'course.edit',$condition->requiredTask);
        $this->em->wrapInTransaction(function () use ($condition): void {
            if ($this->em->getConnection()->getDatabasePlatform() instanceof PostgreSQLPlatform) {
                $this->em->getConnection()->executeQuery('SELECT pg_advisory_xact_lock(:key)', ['key' => self::GRAPH_LOCK]);
            }
            if (!$condition->task?->id || !$condition->requiredTask?->id) {
                throw new \DomainException('Выберите существующее задание и обязательного предшественника.');
            }
            $edges = $this->em->getConnection()->fetchAllAssociative('SELECT id, task_id, required_task_id FROM quest_prerequisite');
            $next = [];
            foreach ($edges as $edge) {
                if ($condition->id !== null && (int) $edge['id'] === $condition->id) { continue; }
                $next[(int) $edge['task_id']] = (int) $edge['required_task_id'];
            }
            if (isset($next[$condition->task->id])) {
                throw new \DomainException('У задания уже есть обязательный предшественник.');
            }
            $next[$condition->task->id] = $condition->requiredTask->id;
            $finished = [];
            foreach (array_keys($next) as $start) {
                $chain = []; $current = $start;
                while (isset($next[$current]) && !isset($finished[$current])) {
                    if (isset($chain[$current])) { throw new \DomainException('Циклическая зависимость запрещена, включая зависимость задания от самого себя.'); }
                    $chain[$current] = true;
                    $current = $next[$current];
                }
                $finished += $chain;
            }
            $this->em->persist($condition);
        });
    }
}
