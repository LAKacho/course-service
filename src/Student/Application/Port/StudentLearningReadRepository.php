<?php
declare(strict_types=1);

namespace App\Student\Application\Port;

use App\Learning\Domain\Entity\Task;

interface StudentLearningReadRepository
{
    /** @return list<Task> */
    public function assignedTasks(string $userId, ?int $taskId = null): array;
    /** @return array<int, true> */
    public function completedIds(string $userId): array;
    /** @return array<int, true> */
    public function startedIds(string $userId): array;
    /** @return array<int, int> Task ID to required task ID. */
    public function predecessors(array $taskIds): array;
    /** @return array{learningPaths: list<array{id: int, title: string}>, courses: list<array{id: int, title: string}>} */
    public function programs(string $userId): array;
    /** @return array<int, array{taskId: int, chapterId: int, chapterTitle: string, courseTitle: string}> */
    public function chaptersForVisibleTasks(array $taskIds): array;
}
