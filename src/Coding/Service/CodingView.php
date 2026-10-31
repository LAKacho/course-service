<?php
declare(strict_types=1);
namespace App\Coding\Service;
use App\Coding\Entity\{CodeSubmission, ExerciseVersion};
use Doctrine\ORM\EntityManagerInterface;
final class CodingView
{
    public const STATUSES = ['draft' => 'Черновик', 'submitted' => 'Отправлено', 'queued' => 'В очереди', 'running' => 'Проверяется',
        'passed' => 'Проверка пройдена', 'failed' => 'Тесты не пройдены', 'error' => 'Ошибка проверки', 'timeout' => 'Время истекло'];
    public function __construct(private EntityManagerInterface $em) {}
    public function exercise(ExerciseVersion $version): array
    {
        return ['id' => $version->id, 'taskId' => $version->task->id, 'version' => $version->version, 'title' => $version->title,
            'statement' => $version->statement, 'starters' => $version->starters, 'limits' => $version->limits,
            'publicTests' => $version->publicTests, 'criteria' => 'Все публичные и скрытые тесты должны пройти.'];
    }
    public function submission(CodeSubmission $submission): array
    {
        return ['id' => $submission->publicId, 'taskId' => $submission->exercise->task->id, 'version' => $submission->exercise->version,
            'language' => $submission->language, 'sourceCode' => $submission->sourceCode, 'status' => $submission->status,
            'statusLabel' => self::STATUSES[$submission->status], 'mode' => $submission->mode, 'createdAt' => $submission->createdAt,
            'checkedAt' => $submission->checkedAt, 'score' => $submission->score, 'publicResults' => $submission->publicResults];
    }
    public function history(ExerciseVersion $version, string $userId): array
    {
        $rows = $this->em->createQuery('SELECT s FROM App\Coding\Entity\CodeSubmission s JOIN s.exercise e WHERE e.task = :task AND s.userId = :user ORDER BY s.id DESC')
            ->setParameter('task', $version->task)->setParameter('user', $userId)->setMaxResults(30)->getResult();
        return array_map($this->submission(...), $rows);
    }
}
