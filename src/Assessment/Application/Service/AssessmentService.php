<?php
namespace App\Assessment\Application\Service;
use App\Assessment\Domain\Entity\{Assessment, AssessmentAttempt, UserAnswer, Question, AssessmentResult, SkillAssessment, Skill};
use App\Assessment\Application\Port\EntryAssessment;
use App\Assessment\Application\Port\AssessmentRepository;
use App\Assessment\Domain\Service\ScoringService;
use App\Shared\Application\Exception\{Conflict,InvalidInput,ResourceNotVisible};

class AssessmentService implements EntryAssessment
{
    public function __construct(private AssessmentRepository $repository, private AttemptSnapshotter $snapshotter, private ScoringService $scoring) {}

    public function owned(string $publicId, string $userId): AssessmentAttempt
    {
        return $this->repository->ownedAttempt($publicId,$userId) ?? throw new ResourceNotVisible('Попытка не найдена.');
    }

    public function start(Assessment $assessment, string $userId): AssessmentAttempt
    {
        if (trim($userId) === '' || mb_strlen($userId) > 100) { throw new \InvalidArgumentException('Некорректная серверная идентичность.'); }
        return $this->repository->transaction(function () use ($assessment, $userId): AssessmentAttempt {
            $this->repository->lock($assessment);
            $active = $this->repository->activeAttempt($assessment,$userId);
            if ($active) { return $active; }
            $attempt = new AssessmentAttempt(); $attempt->assessment = $assessment; $attempt->userId = $userId;
            $attempt->activeKey = hash('sha256', $userId."\0".$assessment->code);
            $attempt->snapshot = $this->snapshotter->capture($assessment);
            $this->repository->persist($attempt);
            return $attempt;
        });
    }

    public function answers(AssessmentAttempt $attempt): array
    {
        $result = [];
        foreach ($this->repository->answers($attempt) as $answer) {
            $result[$answer->questionKey] = $answer->selectedOptions;
        }
        return $result;
    }

    public function answer(AssessmentAttempt $attempt, string $userId, string $questionKey, array $selected): void
    {
        $this->assertOwner($attempt, $userId);
        $this->repository->transaction(function () use ($attempt, $userId, $questionKey, $selected): void {
            $this->repository->lock($attempt); $this->repository->refresh($attempt);
            $this->assertOwner($attempt, $userId);
            if (!$selected || count($selected) !== count(array_unique($selected, SORT_REGULAR)) || array_filter($selected, static fn($value): bool => !is_string($value))) {
                throw new InvalidInput('Выберите допустимый ответ без повторов.');
            }
            sort($selected, SORT_STRING);
            $answers = $this->answers($attempt);
            if (isset($answers[$questionKey])) {
                $previous = $answers[$questionKey]; sort($previous, SORT_STRING);
                if ($previous === $selected) { return; }
                throw new Conflict('Ответ уже сохранён. Изменение принятого ответа не допускается.');
            }
            if ($attempt->status !== 'in_progress') { throw new Conflict('Попытка уже завершена.'); }
            $question = $attempt->snapshot['questions'][count($answers)] ?? null;
            if (!$question || $question['key'] !== $questionKey) { throw new ResourceNotVisible('Вопрос недоступен.'); }
            if (array_diff($selected, array_column($question['options'], 'code')) || ($question['type'] !== 'multiple_choice' && count($selected) !== 1)) {
                throw new InvalidInput('Выбран недопустимый вариант ответа.');
            }
            $answer = new UserAnswer(); $answer->attempt = $attempt; $answer->questionKey = $questionKey;
            $answer->question = $this->repository->question($question['id']); $answer->selectedOptions = $selected;
            $this->repository->persist($answer);
        });
    }

    public function finish(AssessmentAttempt $attempt, string $userId): void
    {
        $this->assertOwner($attempt, $userId);
        $this->repository->transaction(function () use ($attempt): void {
            $this->repository->lock($attempt); $this->repository->refresh($attempt);
            if ($attempt->status === 'completed') { return; }
            $answers = $this->answers($attempt);
            if (count($answers) !== count($attempt->snapshot['questions'])) { throw new Conflict('Сначала ответьте на все вопросы теста.'); }
            $score = $this->scoring->score($attempt->snapshot, $answers);
            $result = new AssessmentResult(); $result->attempt = $attempt;
            foreach ($score as $field => $value) { if ($field !== 'skills') { $result->{$field} = $value; } }
            $this->repository->persist($result);
            foreach ($score['skills'] as $row) {
                $skill = new SkillAssessment(); $skill->result = $result;
                $skill->skill = $this->repository->skill($row['id']);
                $skill->skillCode = $row['code']; $skill->skillTitle = $row['title']; $skill->recommendation = $row['recommendation'];
                foreach (['questionCount', 'correctCount', 'weightedPercent', 'confidence'] as $field) { $skill->{$field} = $row[$field]; }
                $this->repository->persist($skill);
            }
            $attempt->status = 'completed'; $attempt->activeKey = null; $attempt->completedAt = new \DateTimeImmutable();
        });
    }

    private function assertOwner(AssessmentAttempt $attempt, string $userId): void
    {
        if ($attempt->userId !== $userId) { throw new ResourceNotVisible('Попытка не найдена.'); }
    }
}
