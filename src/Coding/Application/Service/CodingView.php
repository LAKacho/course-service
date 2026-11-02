<?php
declare(strict_types=1);
namespace App\Coding\Application\Service;
use App\Coding\Domain\Entity\{CodeSubmission, ExerciseVersion};
use App\Coding\Application\Port\CodingRepository;
final class CodingView
{
    public const STATUSES = ['draft' => 'Черновик', 'submitted' => 'Отправлено', 'queued' => 'В очереди', 'running' => 'Проверяется',
        'passed' => 'Проверка пройдена', 'failed' => 'Тесты не пройдены', 'error' => 'Ошибка проверки', 'timeout' => 'Время истекло'];
    public function __construct(private CodingRepository $repository) {}
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
        $rows = $this->repository->history($version->task,$userId);
        return array_map($this->submission(...), $rows);
    }
}
