<?php
namespace App\Student\Application\Service;

use App\Learning\Domain\Entity\Task;
use App\Student\Domain\QuestState;
use App\Learning\Domain\Service\ProgressCalculator;
use App\Student\Application\Port\StudentLearningReadRepository;
use App\Student\Domain\Service\QuestAvailabilityPolicy;
use App\Shared\Application\Port\LearnerTaskAvailability;

class QuestAvailabilityService implements LearnerTaskAvailability
{
    public function __construct(private StudentLearningReadRepository $repository, private \App\Access\Application\Service\AccessChecker $access, private QuestAvailabilityPolicy $policy) {}

    public function state(Task $task, string $userId): QuestState
    {
        $states = $this->states($userId, $task->id);
        return $states[$task->id]['state'] ?? QuestState::Locked;
    }

    public function isAvailable(Task $task, string $userId): bool
    {
        return $this->state($task, $userId) !== QuestState::Locked;
    }

    /** Internal task objects must never be serialized wholesale to students. */
    public function states(string $userId, ?int $taskId = null): array
    {
        $tasks = $this->repository->assignedTasks($userId, $taskId);
        if (!$tasks) { return []; }
        $completed = $this->repository->completedIds($userId);
        $started = $this->repository->startedIds($userId);
        $required = $this->repository->predecessors(array_map(static fn(Task $task): int => $task->id, $tasks));
        $result = [];
        foreach ($tasks as $task) {
            $hasCompletedPrerequisite = !isset($required[$task->id]) || isset($completed[$required[$task->id]]);
            $state = QuestState::from($this->policy->state(
                isset($completed[$task->id]),
                $hasCompletedPrerequisite,
                isset($started[$task->id]),
            ));
            $result[$task->id] = ['task' => $task, 'state' => $state];
        }
        return $result;
    }

    /** Only this explicit projection is used by the student API and student page. */
    public function visibleQuests(string $userId): array
    {
        $result = [];
        foreach ($this->states($userId) as $item) {
            if ($item['state'] === QuestState::Locked) { continue; }
            $task = $item['task'];
            if (!$this->access->can($userId,'task.view',$task)) { continue; }
            $result[] = ['id' => $task->id, 'title' => $task->title, 'description' => $task->description,
                'type' => $task->type, 'contentUrl' => $task->contentUrl, 'state' => $item['state']->value,
                'canSubmit'=>$this->access->can($userId,'task.submit',$task)];
        }
        return $result;
    }

    public function visibleProgress(array $visibleQuests): array
    {
        $counts = array_count_values(array_column($visibleQuests, 'state'));
        $visible = count($visibleQuests);
        $completed = $counts['completed'] ?? 0;
        return ['visible' => $visible, 'available' => $counts['available'] ?? 0,
            'inProgress' => $counts['in_progress'] ?? 0, 'completed' => $completed,
            'percentVisible' => ProgressCalculator::percentage($visible, $completed)];
    }
}
