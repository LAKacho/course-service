<?php
declare(strict_types=1);
namespace App\Student\Application\Service;

use App\Shared\Application\Exception\ResourceNotVisible;
use App\Learning\Domain\Entity\Task;
use App\Student\Domain\QuestState;
use App\Learning\Application\Service\ProgressService;
use App\Shared\Application\Port\CurrentUserId;
use App\Student\Application\Port\StudentQuestStore;

final class StudentQuestService
{
    public function __construct(private StudentQuestStore $store,private CurrentUserId $user,
        private QuestAvailabilityService $availability,private ProgressService $progress) {}
    public function start(Task $task): void { $this->transition($task,false); }
    public function complete(Task $task): void { $this->transition($task,true); }
    private function transition(Task $task,bool $complete): void
    {
        $this->store->withTransitionLock($task,function() use($task,$complete): void {
            $userId=$this->user->id();
            $state=$this->availability->state($task,$userId);
            if($state===QuestState::Locked)throw new ResourceNotVisible('Задание не найдено.');
            if($state===QuestState::Completed)return;
            if($complete){$this->progress->setCompleted($task,true);return;}
            if(!$this->store->hasStarted($task,$userId))$this->store->recordStart($task,$userId);
        });
    }
}
