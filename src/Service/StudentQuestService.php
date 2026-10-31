<?php
namespace App\Service;

use App\Entity\{Task, QuestActivity, QuestState};
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StudentQuestService
{
    public function __construct(private EntityManagerInterface $em, private CurrentUser $user,
        private QuestAvailabilityService $availability, private ProgressService $progress) {}

    public function start(Task $task): void { $this->transition($task, false); }
    public function complete(Task $task): void { $this->transition($task, true); }

    private function transition(Task $task, bool $complete): void
    {
        $this->em->wrapInTransaction(function () use ($task, $complete): void {
            if ($this->em->getConnection()->getDatabasePlatform() instanceof PostgreSQLPlatform) {
                $this->em->getConnection()->executeQuery('SELECT pg_advisory_xact_lock_shared(:key)', ['key' => QuestPrerequisiteService::GRAPH_LOCK]);
            }
            $this->em->lock($task, LockMode::PESSIMISTIC_WRITE);
            $state = $this->availability->state($task, $this->user->id());
            if ($state === QuestState::Locked) { throw new NotFoundHttpException('Задание не найдено.'); }
            if ($state === QuestState::Completed) { return; }
            if ($complete) {
                // One source of completion truth, same transaction and same CurrentUser.
                $this->progress->setCompleted($task, true);
            } elseif (!$this->em->getRepository(QuestActivity::class)->findOneBy(['task' => $task, 'userId' => $this->user->id()])) {
                $this->em->persist(new QuestActivity($this->user->id(), $task));
            }
        });
    }
}
