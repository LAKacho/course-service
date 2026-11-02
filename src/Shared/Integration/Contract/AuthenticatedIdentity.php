<?php
namespace App\Integration\Contract;

/** Proposed internal value, NOT an Authorization Service response schema. */
final readonly class AuthenticatedIdentity
{
    public function __construct(public string $subject) {}
}
