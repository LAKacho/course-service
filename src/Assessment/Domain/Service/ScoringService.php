<?php
namespace App\Assessment\Domain\Service;

/** Deterministic rubric, not psychometrics or professional certification. */
class ScoringService
{
    public const VERSION = 'entry-v1';

    public function score(array $snapshot, array $answers): array
    {
        if (($snapshot['methodVersion'] ?? self::VERSION) !== self::VERSION) {
            throw new \DomainException('Версия методики попытки не поддерживается. Нельзя молча пересчитать её другой методикой.');
        }
        $correct = 0; $earned = 0; $possible = 0; $skills = [];
        $difficulty = array_fill_keys(['beginner', 'junior', 'middle', 'senior'], ['tested' => 0, 'correct' => 0]);
        foreach ($snapshot['questions'] as $question) {
            $expected = $question['correctAnswer']; $selected = $answers[$question['key']] ?? [];
            sort($expected); sort($selected);
            $ok = $selected === $expected;
            $weight = $question['weight']; $possible += $weight;
            if ($ok) { ++$correct; $earned += $weight; }
            ++$difficulty[$question['difficulty']]['tested'];
            if ($ok) { ++$difficulty[$question['difficulty']]['correct']; }
            foreach ($question['skills'] as $skill) {
                $code = $skill['code'];
                $skills[$code] ??= $skill + ['questionCount' => 0, 'correctCount' => 0, 'earned' => 0, 'possible' => 0];
                ++$skills[$code]['questionCount']; $skills[$code]['possible'] += $weight;
                if ($ok) { ++$skills[$code]['correctCount']; $skills[$code]['earned'] += $weight; }
            }
        }
        $total = count($snapshot['questions']);
        $percent = $total ? round(100 * $correct / $total, 1) : 0.0;
        $weighted = $possible ? round(100 * $earned / $possible, 1) : 0.0;
        $foundationN = $difficulty['beginner']['tested'] + $difficulty['junior']['tested'];
        $foundationCorrect = $difficulty['beginner']['correct'] + $difficulty['junior']['correct'];
        $rate = static fn(array $row): float => $row['tested'] ? 100 * $row['correct'] / $row['tested'] : 0;
        $coverage = true;
        foreach ($snapshot['coreSkills'] as $code) { if (($skills[$code]['questionCount'] ?? 0) < 2) { $coverage = false; } }
        $level = 'beginner';
        if ($foundationN >= 8 && $difficulty['junior']['tested'] >= 4 && $rate($difficulty['junior']) >= 60
            && 100 * $foundationCorrect / $foundationN >= 65 && $percent >= 55) { $level = 'junior_candidate'; }
        if ($level === 'junior_candidate' && $total >= 20 && $difficulty['middle']['tested'] >= 6
            && $rate($difficulty['middle']) >= 60 && $weighted >= 70 && $coverage) { $level = 'middle_candidate'; }
        $confidence = $total < 10 ? 'insufficient' : (($total >= 20 && $difficulty['middle']['tested'] >= 6 && $coverage) ? 'moderate' : 'low');
        foreach ($skills as &$skill) {
            $skill['weightedPercent'] = round(100 * $skill['earned'] / $skill['possible'], 1);
            $skill['confidence'] = $skill['questionCount'] < 3 ? 'insufficient' : ($skill['questionCount'] >= 6 ? 'moderate' : 'low');
            unset($skill['earned'], $skill['possible']);
        }
        unset($skill);
        return ['methodVersion' => self::VERSION, 'questionCount' => $total, 'correctCount' => $correct,
            'percentCorrect' => (float) $percent, 'weightedPercent' => (float) $weighted, 'preliminaryLevel' => $level,
            'confidence' => $confidence, 'difficultyBreakdown' => $difficulty, 'skills' => array_values($skills)];
    }
}
