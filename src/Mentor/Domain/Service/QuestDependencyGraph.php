<?php
declare(strict_types=1);
namespace App\Mentor\Domain\Service;

/** Validates that each task has at most one prerequisite and that the graph is acyclic. */
final class QuestDependencyGraph
{
    /** @param list<array{id:int,task_id:int,required_task_id:int}> $edges */
    public function assertCanReplace(array $edges, ?int $replacedEdgeId, int $taskId, int $requiredTaskId): void
    {
        $next = [];
        foreach ($edges as $edge) {
            if ($replacedEdgeId !== null && (int) $edge['id'] === $replacedEdgeId) { continue; }
            $next[(int) $edge['task_id']] = (int) $edge['required_task_id'];
        }
        if (isset($next[$taskId])) {
            throw new \DomainException('У задания уже есть обязательный предшественник.');
        }
        $next[$taskId] = $requiredTaskId;
        $finished = [];
        foreach (array_keys($next) as $start) {
            $chain = []; $current = $start;
            while (isset($next[$current]) && !isset($finished[$current])) {
                if (isset($chain[$current])) {
                    throw new \DomainException('Циклическая зависимость запрещена, включая зависимость задания от самого себя.');
                }
                $chain[$current] = true;
                $current = $next[$current];
            }
            $finished += $chain;
        }
    }
}
