<?php
namespace App\Assessment\Service;
use App\Assessment\Entity\{Assessment, AssessmentAttempt, UserAnswer, Question, AssessmentResult, SkillAssessment, Skill};
use App\Assessment\Port\EntryAssessment;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\{NotFoundHttpException, ConflictHttpException, BadRequestHttpException};

class AssessmentService implements EntryAssessment
{
    public function __construct(private EntityManagerInterface $em, private AttemptSnapshotter $snapshotter, private ScoringService $scoring) {}

    public function owned(string $publicId, string $userId): AssessmentAttempt
    {
        return $this->em->getRepository(AssessmentAttempt::class)->findOneBy(['publicId' => $publicId, 'userId' => $userId]) ?? throw new NotFoundHttpException('Попытка не найдена.');
    }

    public function start(Assessment $assessment, string $userId): AssessmentAttempt
    {
        if (trim($userId) === '' || mb_strlen($userId) > 100) { throw new \InvalidArgumentException('Некорректная серверная идентичность.'); }
        return $this->em->wrapInTransaction(function () use ($assessment, $userId): AssessmentAttempt {
            $this->em->lock($assessment, LockMode::PESSIMISTIC_WRITE);
            $active = $this->em->getRepository(AssessmentAttempt::class)->findOneBy(['assessment' => $assessment, 'userId' => $userId, 'status' => 'in_progress']);
            if ($active) { return $active; }
            $attempt = new AssessmentAttempt(); $attempt->assessment = $assessment; $attempt->userId = $userId;
            $attempt->activeKey = hash('sha256', $userId."\0".$assessment->code);
            $attempt->snapshot = $this->snapshotter->capture($assessment);
            $this->em->persist($attempt);
            return $attempt;
        });
    }

    public function answers(AssessmentAttempt $attempt): array
    {
        $result = [];
        foreach ($this->em->getRepository(UserAnswer::class)->findBy(['attempt' => $attempt], ['id' => 'ASC']) as $answer) {
            $result[$answer->questionKey] = $answer->selectedOptions;
        }
        return $result;
    }

    public function answer(AssessmentAttempt $attempt, string $userId, string $questionKey, array $selected): void
    {
        $this->assertOwner($attempt, $userId);
        $this->em->wrapInTransaction(function () use ($attempt, $userId, $questionKey, $selected): void {
            $this->em->lock($attempt, LockMode::PESSIMISTIC_WRITE); $this->em->refresh($attempt);
            $this->assertOwner($attempt, $userId);
            if (!$selected || count($selected) !== count(array_unique($selected, SORT_REGULAR)) || array_filter($selected, static fn($value): bool => !is_string($value))) {
                throw new BadRequestHttpException('Выберите допустимый ответ без повторов.');
            }
            sort($selected, SORT_STRING);
            $answers = $this->answers($attempt);
            if (isset($answers[$questionKey])) {
                $previous = $answers[$questionKey]; sort($previous, SORT_STRING);
                if ($previous === $selected) { return; }
                throw new ConflictHttpException('Ответ уже сохранён. Изменение принятого ответа не допускается.');
            }
            if ($attempt->status !== 'in_progress') { throw new ConflictHttpException('Попытка уже завершена.'); }
            $question = $attempt->snapshot['questions'][count($answers)] ?? null;
            if (!$question || $question['key'] !== $questionKey) { throw new NotFoundHttpException('Вопрос недоступен.'); }
            if (array_diff($selected, array_column($question['options'], 'code')) || ($question['type'] !== 'multiple_choice' && count($selected) !== 1)) {
                throw new BadRequestHttpException('Выбран недопустимый вариант ответа.');
            }
            $answer = new UserAnswer(); $answer->attempt = $attempt; $answer->questionKey = $questionKey;
            $answer->question = $this->em->find(Question::class, $question['id']); $answer->selectedOptions = $selected;
            $this->em->persist($answer);
        });
    }

    public function finish(AssessmentAttempt $attempt, string $userId): void
    {
        $this->assertOwner($attempt, $userId);
        $this->em->wrapInTransaction(function () use ($attempt): void {
            $this->em->lock($attempt, LockMode::PESSIMISTIC_WRITE); $this->em->refresh($attempt);
            if ($attempt->status === 'completed') { return; }
            $answers = $this->answers($attempt);
            if (count($answers) !== count($attempt->snapshot['questions'])) { throw new ConflictHttpException('Сначала ответьте на все вопросы теста.'); }
            $score = $this->scoring->score($attempt->snapshot, $answers);
            $result = new AssessmentResult(); $result->attempt = $attempt;
            foreach ($score as $field => $value) { if ($field !== 'skills') { $result->{$field} = $value; } }
            $this->em->persist($result);
            foreach ($score['skills'] as $row) {
                $skill = new SkillAssessment(); $skill->result = $result;
                $skill->skill = $this->em->find(Skill::class, $row['id']);
                $skill->skillCode = $row['code']; $skill->skillTitle = $row['title']; $skill->recommendation = $row['recommendation'];
                foreach (['questionCount', 'correctCount', 'weightedPercent', 'confidence'] as $field) { $skill->{$field} = $row[$field]; }
                $this->em->persist($skill);
            }
            $attempt->status = 'completed'; $attempt->activeKey = null; $attempt->completedAt = new \DateTimeImmutable();
        });
    }

    private function assertOwner(AssessmentAttempt $attempt, string $userId): void
    {
        if ($attempt->userId !== $userId) { throw new NotFoundHttpException('Попытка не найдена.'); }
    }
}
