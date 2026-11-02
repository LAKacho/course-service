<?php
namespace App\Integration\Contract;

/** A real future adapter may return a service reference; no fake tickets are generated. */
final readonly class EvaluationHandle
{
    public function __construct(public string $reference) {}
}
