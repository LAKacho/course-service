<?php
declare(strict_types=1);
namespace App\Mentor\Application\Port;
use App\Mentor\Domain\Entity\QuestPrerequisite;
interface QuestPrerequisiteStore
{
    public function withGraphWriteLock(callable $operation): mixed;
    /** @return list<array{id:int,task_id:int,required_task_id:int}> */
    public function edges(): array;
    public function save(QuestPrerequisite $condition): void;
}
