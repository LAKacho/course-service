<?php
declare(strict_types=1);
namespace App\Coding\Contract;
/** Adapter-only trusted DTO. Public outputs intentionally exclude logs and hidden case details. */
final readonly class RunnerResult
{
    public function __construct(public string $jobId, public string $submissionId, public string $testsVersion,
        public string $status, public array $publicResults = [], public ?bool $allTestsPassed = null) {}
}
