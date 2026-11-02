<?php
declare(strict_types=1);
namespace App\Assessment\Application\Port;
use App\Assessment\Domain\Entity\{AnswerOption,Assessment,AssessmentAttempt,AssessmentResult,Question,Skill,SkillAssessment,UserAnswer};
interface AssessmentRepository
{
    public function transaction(callable $operation): mixed;
    public function lock(object $entity): void;
    public function refresh(object $entity): void;
    /** @return list<Assessment> */ public function catalog(): array;
    public function ownedAttempt(string $publicId,string $userId): ?AssessmentAttempt;
    public function activeAttempt(Assessment $assessment,string $userId): ?AssessmentAttempt;
    /** @return list<UserAnswer> */ public function answers(AssessmentAttempt $attempt): array;
    public function question(int $id): ?Question;
    public function skill(int $id): ?Skill;
    /** @return list<AnswerOption> */ public function options(Question $question): array;
    public function result(AssessmentAttempt $attempt): ?AssessmentResult;
    public function skillByCode(string $code): ?Skill;
    public function questionByCode(string $code): ?Question;
    public function assessmentByCode(string $code): ?Assessment;
    public function addQuestionToAssessment(Assessment $assessment, Question $question): void;
    public function addSkillToQuestion(Question $question, Skill $skill): void;
    /** @return list<SkillAssessment> */ public function skillResults(AssessmentResult $result): array;
    public function persist(object $entity): void;
}
