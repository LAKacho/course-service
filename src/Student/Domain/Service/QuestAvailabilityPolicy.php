<?php
declare(strict_types=1);
namespace App\Student\Domain\Service;

/** Resolves a quest's learner-facing lifecycle state from persisted facts. */
final class QuestAvailabilityPolicy
{
    public function state(bool $completed, bool $prerequisiteCompleted, bool $started): string
    {
        if ($completed) { return 'completed'; }
        if (!$prerequisiteCompleted) { return 'locked'; }
        if ($started) { return 'in_progress'; }
        return 'available';
    }
}
