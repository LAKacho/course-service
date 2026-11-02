<?php
declare(strict_types=1);
namespace App\Coding\Application\Port;
use App\Coding\Domain\Entity\{CodeSubmission,ExerciseVersion};
use App\Learning\Domain\Entity\Task;
use App\Learning\Domain\Entity\TaskProgress;
interface CodingRepository
{
    public function transaction(callable $operation): mixed;
    public function lock(object $entity): void;
    public function refresh(object $entity): void;
    public function latestExercise(Task $task): ?ExerciseVersion;
    public function taskHasProgress(Task $task): bool;
    public function ownedSubmission(string $publicId,string $userId): ?CodeSubmission;
    /** @return list<CodeSubmission> */
    public function history(Task $task,string $userId): array;
    public function hasProgress(Task $task,string $userId): bool;
    public function persist(object $entity): void;
    public function save(object $entity): void;
    public function flush(): void;
}
