<?php
namespace App\Assessment\Application\Service;
use App\Assessment\Domain\Entity\{Assessment,AssessmentAttempt};
use App\Assessment\Application\Port\AssessmentRepository;
use App\Shared\Application\Exception\{Conflict,ResourceNotVisible};

/** Explicit student projections. Snapshot, answer keys and explanations never leave here. */
class AssessmentView
{
    public const LEVELS = ['beginner' => 'Начальный уровень', 'junior_candidate' => 'Предварительно Junior', 'middle_candidate' => 'Предварительно Middle'];
    public const CONFIDENCE = ['insufficient' => 'Данных недостаточно', 'low' => 'Ограниченная', 'moderate' => 'Умеренная'];
    public function __construct(private AssessmentRepository $repository, private AssessmentService $service) {}
    public function catalog(): array
    {
        return array_map(static fn(Assessment $assessment): array => ['code' => $assessment->code, 'title' => $assessment->title,
            'description' => $assessment->description, 'questionCount' => count($assessment->questions)], $this->repository->catalog());
    }
    public function attempt(AssessmentAttempt $attempt): array
    {
        $answered = count($this->service->answers($attempt)); $total = count($attempt->snapshot['questions']);
        $question = $attempt->status === 'in_progress' ? ($attempt->snapshot['questions'][$answered] ?? null) : null;
        return ['id' => $attempt->publicId, 'title' => $attempt->snapshot['assessmentTitle'], 'direction' => $attempt->snapshot['assessmentCode'],
            'status' => $attempt->status, 'answered' => $answered, 'total' => $total, 'percent' => $total ? (int) round(100 * $answered / $total) : 0,
            'currentQuestion' => $question ? $this->question($question) : null];
    }
    public function questionForAnswer(AssessmentAttempt $attempt, string $key): array
    {
        $answers = $this->service->answers($attempt);
        foreach ($attempt->snapshot['questions'] as $index => $question) {
            if ($question['key'] === $key && (isset($answers[$key]) || ($attempt->status === 'in_progress' && $index === count($answers)))) { return $this->question($question); }
        }
        throw new ResourceNotVisible('Вопрос недоступен.');
    }
    private function question(array $question): array
    {
        return array_intersect_key($question, array_flip(['key', 'type', 'text', 'codeSnippet', 'language', 'options']));
    }
    public function result(AssessmentAttempt $attempt): array
    {
        if ($attempt->status !== 'completed') { throw new Conflict('Результат появится после завершения теста.'); }
        $result = $this->repository->result($attempt) ?? throw new ResourceNotVisible('Результат не найден.');
        $skills = []; $recommendations = []; $strengths = [];
        foreach ($this->repository->skillResults($result) as $row) {
            $item = ['code' => $row->skillCode, 'title' => $row->skillTitle, 'questionCount' => $row->questionCount,
                'correctCount' => $row->correctCount, 'percent' => $row->weightedPercent, 'confidence' => self::CONFIDENCE[$row->confidence]];
            $skills[] = $item;
            if ($row->weightedPercent < 60) { $recommendations[] = $item + ['topic' => $row->recommendation]; }
            if ($row->weightedPercent >= 80 && $row->questionCount >= 3) { $strengths[] = $item; }
        }
        return ['id' => $attempt->publicId, 'title' => $attempt->snapshot['assessmentTitle'], 'methodVersion' => $result->methodVersion,
            'questionCount' => $result->questionCount, 'correctCount' => $result->correctCount,
            'percentCorrect' => $result->percentCorrect, 'weightedPercent' => $result->weightedPercent,
            'level' => $result->preliminaryLevel, 'levelLabel' => self::LEVELS[$result->preliminaryLevel],
            'confidence' => self::CONFIDENCE[$result->confidence], 'difficultyBreakdown' => $result->difficultyBreakdown,
            'skills' => $skills, 'strengths' => $strengths, 'recommendations' => $recommendations,
            'notice' => 'Это предварительный профиль по ограниченному тесту, не подтверждение квалификации. Senior требует практической и менторской оценки.'];
    }
}
