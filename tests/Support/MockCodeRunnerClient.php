<?php
declare(strict_types=1);
namespace App\Tests\Support;
use App\Coding\Contract\{CodeRunnerClient, RunRequest, RunnerResult};
/** PHPUnit-only deterministic transport double; never registered in application DI. */
final class MockCodeRunnerClient implements CodeRunnerClient
{
    public array $requests = [];
    public ?RunnerResult $result = null;
    public bool $online = true;
    public bool $throws = false;
    public function available(): bool { return $this->online; }
    public function submit(RunRequest $request): string
    {
        $this->requests[] = $request;
        if ($this->throws) { throw new \RuntimeException('Transport failed'); }
        return 'job_'.$request->submissionId;
    }
    public function poll(string $jobId): ?RunnerResult { return $this->result; }
}
