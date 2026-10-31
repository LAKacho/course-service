<?php
declare(strict_types=1);
namespace App\Coding\Service;
use App\Coding\Contract\{CodeRunnerClient, RunRequest, RunnerResult};
use App\Coding\Entity\{CodeSubmission, ExerciseVersion};
use App\Entity\{Task, TaskProgress, QuestState};
use App\Service\QuestAvailabilityService;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\{NotFoundHttpException, ConflictHttpException};

final class SubmissionService
{
    public function __construct(private EntityManagerInterface $em, private QuestAvailabilityService $availability,
        private CodeRunnerClient $runner, private SubmissionTransitions $transitions, private \App\Access\ResourcePolicy $policy) {}
    public function available(): bool { return $this->runner->available(); }
    public function exercise(Task $task, string $userId): ExerciseVersion
    {
        $this->policy->learner($userId,$task,'task.view');
        if ($task->type !== 'coding' || $this->availability->state($task, $userId) === QuestState::Locked) { throw new NotFoundHttpException('Задание не найдено.'); }
        return $this->em->getRepository(ExerciseVersion::class)->findOneBy(['task' => $task], ['version' => 'DESC']) ?? throw new NotFoundHttpException('Версия задания не опубликована.');
    }
    public function owned(string $publicId, string $userId): CodeSubmission
    {
        return $this->em->getRepository(CodeSubmission::class)->findOneBy(['publicId' => $publicId, 'userId' => $userId]) ?? throw new NotFoundHttpException('Решение не найдено.');
    }
    public function save(Task $task, string $userId, int $versionId, string $language, string $source, string $mode): CodeSubmission
    {
        $this->policy->learner($userId,$task,'task.submit');
        $exercise = $this->exercise($task, $userId);
        if ($exercise->id !== $versionId) { throw new ConflictHttpException('Версия задания обновилась. Перезагрузите страницу; код можно скопировать перед обновлением.'); }
        if (!array_key_exists($language, $exercise->starters)) { throw new \InvalidArgumentException('Язык не разрешён для этого задания.'); }
        if (trim($source) === '' || strlen($source) > 65536 || !mb_check_encoding($source, 'UTF-8')) { throw new \InvalidArgumentException('Введите код в UTF-8, не более 64 КиБ.'); }
        if (!in_array($mode, ['draft', 'check', 'submit'], true)) { throw new \InvalidArgumentException('Неизвестное действие.'); }
        $submission = new CodeSubmission(); $submission->exercise = $exercise; $submission->userId = $userId;
        $submission->language = $language; $submission->sourceCode = $source; $submission->mode = $mode;
        $this->em->persist($submission); $this->em->flush();
        if ($mode === 'draft' || !$this->runner->available()) { return $submission; }
        $this->transitions->move($submission, 'submitted'); $this->em->flush();
        try {
            $tests = $mode === 'check' ? $exercise->publicTests : array_merge($exercise->publicTests, $exercise->hiddenTests);
            $job = $this->runner->submit(new RunRequest($submission->publicId, $exercise->id, $exercise->version, $exercise->testsVersion,
                $mode, $language, $source, $tests, $exercise->limits));
            if (!preg_match('/^[a-zA-Z0-9_-]{1,100}$/D', $job)) { throw new \RuntimeException('Invalid runner job identity.'); }
            $submission->runnerJobId = $job; $this->transitions->move($submission, 'queued');
        } catch (\Throwable) {
            // An uncertain dispatch is terminal error locally; never invent a passing result or retry blindly.
            $this->transitions->move($submission, 'error');
        }
        $this->em->flush();
        return $submission;
    }
    /** Server-side polling only. No result/status/score arguments accepted from HTTP. */
    public function refresh(CodeSubmission $submission, string $userId): void
    {
        if ($submission->userId !== $userId) { throw new NotFoundHttpException('Решение не найдено.'); }
        if (!$submission->runnerJobId || in_array($submission->status, SubmissionTransitions::TERMINAL, true) || !$this->runner->available()) { return; }
        try { $result = $this->runner->poll($submission->runnerJobId); } catch (\Throwable) { return; }
        if ($result) { $this->acceptTrustedResult($submission, $result); }
    }
    /** Internal transport boundary. Never expose this method as an unauthenticated callback. */
    public function acceptTrustedResult(CodeSubmission $submission, RunnerResult $result): void
    {
        $this->em->wrapInTransaction(function () use ($submission, $result): void {
            $this->em->lock($submission, LockMode::PESSIMISTIC_WRITE); $this->em->refresh($submission);
            if ($result->jobId !== $submission->runnerJobId || $result->submissionId !== $submission->publicId
                || !hash_equals($submission->exercise->testsVersion, $result->testsVersion)
                || !in_array($result->status, ['running', 'passed', 'failed', 'error', 'timeout'], true)) { throw new \DomainException('Результат runner не соответствует решению.'); }
            $safe = []; $expected = array_column($submission->exercise->publicTests, null, 'id');
            foreach ($result->publicResults as $row) {
                if (!is_array($row) || !isset($row['id']) || !is_string($row['id']) || !isset($expected[$row['id']]) || isset($safe[$row['id']])
                    || !array_key_exists('passed', $row) || !is_bool($row['passed'])) { throw new \DomainException('Некорректные публичные результаты runner.'); }
                // Deliberately discard stdout, messages, expected values and all arbitrary fields.
                $safe[$row['id']] = ['id' => $row['id'], 'passed' => $row['passed']];
            }
            ksort($safe);
            $allPublic = count($safe) === count($expected) && !in_array(false, array_column($safe, 'passed'), true);
            if ($result->status === 'passed' && (!$allPublic || $result->allTestsPassed !== true)) { throw new \DomainException('Успех не подтверждён полным результатом проверки.'); }
            $hash = hash('sha256', json_encode([$result->status, $safe, $result->allTestsPassed], JSON_THROW_ON_ERROR));
            if (in_array($submission->status, SubmissionTransitions::TERMINAL, true)) {
                if ($submission->resultHash === $hash) { return; }
                throw new ConflictHttpException('Итог решения уже зафиксирован.');
            }
            $this->transitions->move($submission, $result->status);
            $submission->publicResults = array_values($safe);
            if (in_array($result->status, SubmissionTransitions::TERMINAL, true)) {
                $submission->resultHash = $hash;
                $submission->score = $result->status === 'passed' ? 100.0 : ($result->status === 'failed' ? 0.0 : null);
                if ($submission->mode === 'submit' && $result->status === 'passed' && $this->policy->canSubmit($submission->userId,$submission->exercise->task)) {
                    $task = $submission->exercise->task;
                    $this->em->lock($task, LockMode::PESSIMISTIC_WRITE);
                    // TaskProgress remains the sole completion truth, for the submission owner, not the polling user.
                    if (!$this->em->getRepository(TaskProgress::class)->findOneBy(['task' => $task, 'userId' => $submission->userId])) {
                        $this->em->persist(new TaskProgress($submission->userId, $task));
                    }
                }
            }
        });
    }
}
