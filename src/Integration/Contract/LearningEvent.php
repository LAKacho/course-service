<?php
namespace App\Integration\Contract;

/** Proposal for internal notification intent; no serializer or wire contract is defined. */
final readonly class LearningEvent
{
    public function __construct(
        public string $eventId,
        public string $name,
        public string $userId,
        public string $resourceReference,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
