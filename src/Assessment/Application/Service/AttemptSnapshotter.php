<?php
namespace App\Assessment\Application\Service;
use App\Assessment\Domain\Service\ScoringService;
use App\Assessment\Domain\Entity\{Assessment, AnswerOption};
use App\Assessment\Application\Port\AssessmentRepository;

class AttemptSnapshotter
{
    public function __construct(private AssessmentRepository $repository) {}
    public function capture(Assessment $assessment): array
    {
        $questions = [];
        foreach ($assessment->questions as $question) {
            $options = $this->repository->options($question);
            if (!$options || !$question->correctAnswer) { throw new \DomainException('Банк вопросов не готов.'); }
            $skills = [];
            foreach ($question->skills as $skill) { $skills[] = ['id' => $skill->id, 'code' => $skill->code, 'title' => $skill->title, 'recommendation' => $skill->recommendation]; }
            $questions[] = ['id' => $question->id, 'key' => $question->code, 'version' => $question->version,
                'type' => $question->type, 'text' => $question->text, 'codeSnippet' => $question->codeSnippet, 'language' => $question->language,
                'difficulty' => $question->difficulty, 'weight' => $question->weight, 'skills' => $skills,
                'options' => array_map(static fn(AnswerOption $option): array => ['code' => $option->code, 'text' => $option->text], $options),
                'correctAnswer' => $question->correctAnswer, 'explanation' => $question->explanation];
        }
        if (!$questions) { throw new \DomainException('В выбранном тестировании ещё нет вопросов.'); }
        return ['methodVersion' => ScoringService::VERSION, 'assessmentCode' => $assessment->code,
            'assessmentTitle' => $assessment->title, 'assessmentVersion' => $assessment->version,
            'coreSkills' => $assessment->coreSkills, 'questions' => $questions];
    }
}
