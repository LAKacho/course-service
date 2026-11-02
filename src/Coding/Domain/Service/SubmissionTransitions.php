<?php
declare(strict_types=1);
namespace App\Coding\Domain\Service;
use App\Coding\Domain\Entity\CodeSubmission;
final class SubmissionTransitions
{
    public const TERMINAL = ['passed', 'failed', 'error', 'timeout'];
    public function move(CodeSubmission $submission, string $next): void
    {
        $allowed = ['draft' => ['submitted'], 'submitted' => ['queued', 'error'],
            'queued' => ['running', 'passed', 'failed', 'error', 'timeout'],
            'running' => ['passed', 'failed', 'error', 'timeout']];
        if ($submission->status === $next) { return; }
        if (!in_array($next, $allowed[$submission->status] ?? [], true)) { throw new \DomainException('Недопустимый переход статуса решения.'); }
        $submission->status = $next;
        if (in_array($next, self::TERMINAL, true)) { $submission->checkedAt = new \DateTimeImmutable(); }
    }
}
