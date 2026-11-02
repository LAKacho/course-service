<?php
namespace App\Assessment\Application\Port;
use App\Assessment\Domain\Entity\{Assessment, AssessmentAttempt};

/** Local entry-screening boundary. Not an implementation of the team's DiagnosticPort. */
interface EntryAssessment
{
    public function start(Assessment $assessment, string $userId): AssessmentAttempt;
    public function answer(AssessmentAttempt $attempt, string $userId, string $questionKey, array $selected): void;
    public function finish(AssessmentAttempt $attempt, string $userId): void;
}
