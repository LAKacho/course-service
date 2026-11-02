<?php
declare(strict_types=1);
namespace App\Learning\Application\Port;
use App\Learning\Domain\Entity\Course;
use App\Learning\Domain\Entity\LearningPath;
use App\Learning\Domain\Entity\PathCourse;
use App\Learning\Domain\Entity\Task;
interface ImportStore
{
    public function transactionForSource(string $source,callable $operation): mixed;
    public function findByImportKey(string $entityClass,string $key): ?object;
    public function findPath(int $id): ?LearningPath;
    public function lockTask(Task $task): void;
    public function findPathBySlug(string $slug): ?LearningPath;
    public function taskHasProgress(Task $task): bool;
    public function findPathCourse(LearningPath $path,Course $course): ?PathCourse;
    public function nextPathCoursePosition(LearningPath $path): int;
    public function persist(object $entity): void;
}
