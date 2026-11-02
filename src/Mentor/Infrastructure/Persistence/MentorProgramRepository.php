<?php
namespace App\Mentor\Infrastructure\Persistence;

use App\Learning\Domain\Entity\LearningPath;
use Doctrine\DBAL\Connection;

/** Deliberately separate, unrestricted mentor outline. Never used by student controllers. */
class MentorProgramRepository
{
    public function __construct(private Connection $connection, private \App\Access\Application\Service\AccessChecker $access, private \App\Auth\Infrastructure\Session\CurrentUser $user, private \Doctrine\ORM\EntityManagerInterface $em) {}

    public function outline(LearningPath $path): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT c.id AS course_id, c.title AS course_title,
            s.id AS sprint_id, s.title AS sprint_title, card.id AS card_id, card.title AS card_title,
            t.id AS task_id, t.title AS task_title, t.type AS task_type
            FROM path_course pc JOIN course c ON c.id = pc.course_id
            LEFT JOIN sprint s ON s.course_id = c.id LEFT JOIN card ON card.sprint_id = s.id
            LEFT JOIN learning_task t ON t.card_id = card.id WHERE pc.path_id = ?
            ORDER BY pc.position, pc.id, s.position, s.id, card.position, card.id, t.position, t.id', [$path->id]);
        $courses = []; $total = 0;
        foreach ($rows as $row) {
            $courseId = (int) $row['course_id'];
            if (!$this->access->can($this->user->id(),'course.edit',$this->em->getReference(\App\Learning\Domain\Entity\Course::class,$courseId))) { continue; }
            $courses[$courseId] ??= ['id' => $courseId, 'title' => $row['course_title'], 'chapters' => []];
            if ($row['sprint_id'] === null) { continue; }
            $chapterId = (int) $row['sprint_id'];
            $courses[$courseId]['chapters'][$chapterId] ??= ['id' => $chapterId, 'title' => $row['sprint_title'], 'cards' => []];
            if ($row['card_id'] === null) { continue; }
            $cardId = (int) $row['card_id'];
            $cards = &$courses[$courseId]['chapters'][$chapterId]['cards'];
            $cards[$cardId] ??= ['id' => $cardId, 'title' => $row['card_title'], 'tasks' => []];
            if ($row['task_id'] !== null) {
                $cards[$cardId]['tasks'][] = ['id' => (int) $row['task_id'], 'title' => $row['task_title'], 'type' => $row['task_type']];
                ++$total;
            }
            unset($cards);
        }
        return ['courses' => $courses, 'total' => $total];
    }
}
