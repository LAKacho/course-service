<?php
declare(strict_types=1);
namespace App\Coding\Application\Port;

use App\Coding\Application\DTO\{RunRequest, RunnerResult};

/** Internal proposal, NOT a specification of an existing external service.
 * An adapter must authenticate the runner, enforce isolation and authenticate results.
 * No browser may supply RunnerResult. Production currently has only unavailable adapter.
 */
interface CodeRunnerClient
{
    public function available(): bool;
    /** Idempotent by submissionId. Must not run code in Symfony or on the host. */
    public function submit(RunRequest $request): string;
    /** Pull over authenticated transport; null means queued. No public callback endpoint. */
    public function poll(string $jobId): ?RunnerResult;
}
