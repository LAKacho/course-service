<?php
namespace App\Mentor\Application\Service;

use App\Mentor\Domain\Entity\QuestPrerequisite;
use App\Access\Application\Service\AccessChecker;
use App\Mentor\Application\Port\QuestPrerequisiteStore;
use App\Mentor\Domain\Service\QuestDependencyGraph;
use App\Shared\Application\Port\CurrentUserId;

class QuestPrerequisiteService
{
    public function __construct(private QuestPrerequisiteStore $store, private AccessChecker $access, private CurrentUserId $user, private QuestDependencyGraph $graph) {}

    public function save(QuestPrerequisite $condition): void
    {
        $this->access->require($this->user->id(),'course.edit',$condition->task);
        $this->access->require($this->user->id(),'course.edit',$condition->requiredTask);
        $this->store->withGraphWriteLock(function () use ($condition): void {
            if (!$condition->task?->id || !$condition->requiredTask?->id) {
                throw new \DomainException('Выберите существующее задание и обязательного предшественника.');
            }
            $edges = $this->store->edges();
            $this->graph->assertCanReplace($edges, $condition->id, $condition->task->id, $condition->requiredTask->id);
            $this->store->save($condition);
        });
    }
}
