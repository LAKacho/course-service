<?php
declare(strict_types=1);
namespace App\Access\Application\Port;

use App\Learning\Domain\Entity\Course;

use App\Learning\Domain\Entity\LearningPath;

use App\Access\Domain\Entity\PermissionGrant;

use App\Learning\Domain\Entity\PathCourse;

use App\Learning\Domain\Entity\Task;

/** Persistence operations used by Access use cases and policies. */
interface AccessRepository
{
    public function courseBelongsToPath(int $pathId, Course $course): bool;
    /** @return list<PathCourse> */
    public function coursesInPath(LearningPath $path): array;
    /** @return list<Course> */
    public function allCourses(): array;
    public function hasEnrollment(string $userId, Course $course): bool;
    public function hasPathEnrollment(string $userId, LearningPath $path): bool;
    /** @return list<LearningPath> */
    public function pathsContainingCourse(Course $course): array;
    /** @return list<Task> */
    public function tasksInContainer(object $container): array;
    public function grantExists(string $userId, string $permission, string $scopeKey): bool;
    public function saveGrant(PermissionGrant $grant): void;
    public function removeGrant(PermissionGrant $grant): void;
}
