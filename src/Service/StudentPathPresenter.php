<?php
namespace App\Service;

use App\Repository\StudentLearningRepository;

/** Presentation only: never decides availability or loads a complete program. */
class StudentPathPresenter
{
    public const TYPES = ['reading' => 'Теория', 'video' => 'Видео', 'practice' => 'Практика', 'question' => 'Вопрос', 'coding' => 'Код'];
    public const STATES = ['locked' => 'Закрыто', 'available' => 'Доступно', 'in_progress' => 'В работе', 'completed' => 'Выполнено'];

    public function __construct(private QuestAvailabilityService $availability, private StudentLearningRepository $repository) {}

    public function present(string $userId): array
    {
        $quests = $this->availability->visibleQuests($userId);
        $metadata = $this->repository->chaptersForVisibleTasks(array_column($quests, 'id'));
        $current = null;
        foreach (['in_progress', 'available'] as $state) {
            foreach ($quests as $quest) {
                if ($quest['state'] === $state) { $current = $quest; break 2; }
            }
        }
        $chapters = [];
        foreach ($quests as &$quest) {
            $meta = $metadata[$quest['id']];
            $quest += ['typeLabel' => self::TYPES[$quest['type']], 'stateLabel' => self::STATES[$quest['state']],
                'chapterTitle' => $meta['chapterTitle'], 'chapterId' => $meta['chapterId'], 'isCurrent' => $current !== null && $current['id'] === $quest['id']];
            $chapterId = $meta['chapterId'];
            $chapters[$chapterId] ??= ['id' => $chapterId, 'title' => $meta['chapterTitle'], 'courseTitle' => $meta['courseTitle'],
                'visible' => 0, 'completed' => 0, 'current' => false, 'quests' => []];
            ++$chapters[$chapterId]['visible'];
            if ($quest['state'] === 'completed') { ++$chapters[$chapterId]['completed']; }
            $chapters[$chapterId]['current'] = $chapters[$chapterId]['current'] || $quest['isCurrent'];
            $chapters[$chapterId]['quests'][] = $quest;
        }
        unset($quest);
        foreach ($chapters as &$chapter) {
            $chapter['knownCompleted'] = $chapter['visible'] === $chapter['completed'];
        }
        unset($chapter);
        // The hero receives the same decorated, already-visible quest as the journal.
        foreach ($quests as $quest) { if ($quest['isCurrent']) { $current = $quest; break; } }
        return ['quests' => $quests, 'chapters' => array_values($chapters), 'currentQuest' => $current,
            'progress' => $this->availability->visibleProgress($quests), 'programs' => $this->repository->programs($userId)];
    }
}
