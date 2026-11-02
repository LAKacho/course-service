<?php
declare(strict_types=1);
namespace App\Shared\Application\Port;

use App\Learning\Domain\Entity\Task;

interface LearnerTaskAvailability
{
    public function isAvailable(Task $task, string $userId): bool;
}
