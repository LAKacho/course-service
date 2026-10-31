<?php
namespace App\Assessment\Service;
use App\Assessment\Entity\{Assessment, AnswerOption};
use Doctrine\ORM\EntityManagerInterface;

class AttemptSnapshotter
{
    public function __construct(private EntityManagerInterface $em) {}
    public function capture(Assessment $assessment): array
    {
        $questions = [];
        foreach ($assessment->questions as $question) {
            $options = $this->em->getRepository(AnswerOption::class)->findBy(['question' => $question], ['position' => 'ASC', 'id' => 'ASC']);
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
