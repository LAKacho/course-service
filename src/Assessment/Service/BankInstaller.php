<?php
namespace App\Assessment\Service;
use App\Assessment\Entity\{Assessment, Question, AnswerOption, Skill};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Additive, idempotent installation of the reviewed original bank. No deletion or silent key changes. */
class BankInstaller
{
    public function __construct(private EntityManagerInterface $em, #[Autowire('%kernel.project_dir%')] private string $root) {}
    public function data(): array
    {
        $data = json_decode(file_get_contents($this->root.'/resources/entry-assessment-bank.json'), true, 64, JSON_THROW_ON_ERROR);
        $seen = [];
        foreach ($data['questions'] as $row) {
            $code = $row['code'] ?? '';
            if (!preg_match('/^[a-z0-9-]{1,60}$/D', $code) || isset($seen[$code])) { throw new \DomainException('Повтор или некорректный код вопроса: '.$code); }
            $seen[$code] = true;
            if (!in_array($row['type'], ['single_choice', 'multiple_choice', 'true_false', 'code_analysis'], true)
                || !in_array($row['difficulty'], ['beginner', 'junior', 'middle', 'senior'], true)
                || !is_int($row['weight']) || $row['weight'] < 1 || $row['weight'] > 5
                || !in_array($row['track'], ['php', 'go', 'all'], true)
                || trim($row['text']) === '' || trim($row['explanation']) === '') { throw new \DomainException('Некорректный вопрос '.$code); }
            $options = $row['options']; $correct = $row['correct'];
            if (count($options) < 2 || count($options) > 8 || !$correct || count($correct) !== count(array_unique($correct)) || array_diff($correct, array_keys($options))) { throw new \DomainException('Некорректные варианты/ключ '.$code); }
            if (($row['type'] === 'multiple_choice' && count($correct) < 2) || ($row['type'] !== 'multiple_choice' && count($correct) !== 1)
                || ($row['type'] === 'true_false' && count($options) !== 2)) { throw new \DomainException('Некорректная кратность ответа '.$code); }
            foreach ($options as $key => $text) {
                if (!preg_match('/^[a-z]{1,20}$/D', $key) || !is_string($text) || trim($text) === '') { throw new \DomainException('Некорректный вариант '.$code); }
            }
            if (!$row['skills'] || array_diff($row['skills'], array_keys($data['skills']))) { throw new \DomainException('Неизвестная компетенция '.$code); }
            if ($row['type'] === 'code_analysis' && empty($row['codeSnippet'])) { throw new \DomainException('Отсутствует фрагмент '.$code); }
        }
        return $data;
    }
    public function install(): array
    {
        $data = $this->data();
        return $this->em->wrapInTransaction(function () use ($data): array {
            $counts = ['skills' => 0, 'questions' => 0, 'assessments' => 0]; $skills = []; $questions = [];
            foreach ($data['skills'] as $code => [$title, $recommendation]) {
                $skill = $this->em->getRepository(Skill::class)->findOneBy(['code' => $code]);
                if (!$skill) { $skill = new Skill(); $skill->code = $code; $skill->title = $title; $skill->recommendation = $recommendation; $this->em->persist($skill); ++$counts['skills']; }
                $skills[$code] = $skill;
            }
            foreach ($data['questions'] as $index => $row) {
                $hash = hash('sha256', json_encode($row, JSON_THROW_ON_ERROR));
                $question = $this->em->getRepository(Question::class)->findOneBy(['code' => $row['code']]);
                if ($question && $question->sourceHash !== $hash) { throw new \DomainException('Банк изменён под существующим кодом '.$row['code'].'. Переиздайте вопрос под новым кодом; автоматической перезаписи нет.'); }
                if (!$question) {
                    $question = new Question();
                    foreach (['code', 'type', 'text', 'difficulty', 'weight', 'explanation'] as $field) { $question->{$field} = $row[$field]; }
                    $question->correctAnswer = $row['correct']; $question->position = $index + 1; $question->sourceHash = $hash;
                    $question->codeSnippet = $row['codeSnippet'] ?? null; $question->language = $row['language'] ?? null;
                    foreach ($row['skills'] as $code) { $question->skills->add($skills[$code]); }
                    $this->em->persist($question);
                    $position = 0;
                    foreach ($row['options'] as $code => $text) { $option = new AnswerOption(); $option->question = $question; $option->code = $code; $option->text = $text; $option->position = ++$position; $this->em->persist($option); }
                    ++$counts['questions'];
                }
                $questions[$row['code']] = $question;
            }
            foreach ($data['assessments'] as $row) {
                $assessment = $this->em->getRepository(Assessment::class)->findOneBy(['code' => $row['code']]);
                if (!$assessment) {
                    $assessment = new Assessment();
                    foreach (['code', 'title', 'description', 'coreSkills'] as $field) { $assessment->{$field} = $row[$field]; }
                    $this->em->persist($assessment); ++$counts['assessments'];
                }
                foreach ($data['questions'] as $questionRow) {
                    if ($row['code'] === 'full_backend' || $questionRow['track'] === 'all' || $row['code'] === $questionRow['track'].'_backend') {
                        $question = $questions[$questionRow['code']];
                        if (!$assessment->questions->contains($question)) { $assessment->questions->add($question); }
                    }
                }
            }
            return $counts;
        });
    }
}
