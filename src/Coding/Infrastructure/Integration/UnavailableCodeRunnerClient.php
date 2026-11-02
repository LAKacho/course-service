<?php
declare(strict_types=1);
namespace App\Coding\Infrastructure\Integration;
use App\Coding\Application\Port\CodeRunnerClient;
use App\Coding\Application\DTO\{RunRequest, RunnerResult};
final class UnavailableCodeRunnerClient implements CodeRunnerClient
{
    public function available(): bool { return false; }
    public function submit(RunRequest $request): string { throw new \RuntimeException('Проверка временно недоступна'); }
    public function poll(string $jobId): ?RunnerResult { throw new \RuntimeException('Проверка временно недоступна'); }
}
