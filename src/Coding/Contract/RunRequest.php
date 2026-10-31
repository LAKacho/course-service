<?php
declare(strict_types=1);
namespace App\Coding\Contract;
final readonly class RunRequest
{
    public function __construct(public string $submissionId, public int $exerciseId, public int $version,
        public string $testsVersion, public string $mode, public string $language, public string $sourceCode,
        public array $tests, public array $limits) {}
}
