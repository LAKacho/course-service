<?php
namespace App\Integration\Contract;

/** Internal proposal only. Storage/format of a submission awaits team agreement. */
final readonly class EvaluationReference
{
    public function __construct(public string $userId, public string $taskReference, public string $submissionReference) {}
}
