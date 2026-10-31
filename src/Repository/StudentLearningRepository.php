<?php
namespace App\Repository;

use App\Entity\{Enrollment, Task, TaskProgress, PathCourse, QuestActivity, QuestPrerequisite};
use Doctrine\ORM\EntityManagerInterface;

class StudentLearningRepository
{
    public function __construct(private EntityManagerInterface $em, private \App\Access\AccessChecker $access) {}

    /** @return list<Task> Membership is checked in SQL before any task is exposed. */
    public function assignedTasks(string $userId, ?int $taskId = null): array
    {
        $direct = 'EXISTS (SELECT direct.id FROM '.Enrollment::class.' direct WHERE direct.userId = :user AND direct.course = course)';
        $throughPath = 'EXISTS (SELECT link.id FROM '.PathCourse::class.' link JOIN '.Enrollment::class.' enrollment WITH enrollment.learningPath = link.path WHERE enrollment.userId = :user AND link.course = course)';
        $query = $this->em->createQueryBuilder()->select('task')->from(Task::class, 'task')
            ->join('task.card', 'card')->join('card.sprint', 'sprint')->join('sprint.course', 'course')
            ->where('('.$direct.' OR '.$throughPath.')')->setParameter('user', $userId)
            ->orderBy('course.id', 'ASC')->addOrderBy('sprint.position', 'ASC')->addOrderBy('sprint.id', 'ASC')
            ->addOrderBy('card.position', 'ASC')->addOrderBy('card.id', 'ASC')
            ->addOrderBy('task.position', 'ASC')->addOrderBy('task.id', 'ASC');
        if ($taskId !== null) { $query->andWhere('task.id = :task')->setParameter('task', $taskId); }
        return $query->getQuery()->getResult();
    }

    public function completedIds(string $userId): array
    {
        $rows = $this->em->createQueryBuilder()->select('IDENTITY(p.task) AS taskId')->from(TaskProgress::class, 'p')
            ->where('p.userId = :user')->setParameter('user', $userId)->getQuery()->getArrayResult();
        return array_fill_keys(array_map('intval', array_column($rows, 'taskId')), true);
    }

    public function startedIds(string $userId): array
    {
        $rows = $this->em->createQueryBuilder()->select('IDENTITY(a.task) AS taskId')->from(QuestActivity::class, 'a')
            ->where('a.userId = :user')->setParameter('user', $userId)->getQuery()->getArrayResult();
        return array_fill_keys(array_map('intval', array_column($rows, 'taskId')), true);
    }

    public function predecessors(array $taskIds): array
    {
        if (!$taskIds) { return []; }
        $rows = $this->em->createQueryBuilder()->select('IDENTITY(p.task) AS taskId', 'IDENTITY(p.requiredTask) AS requiredId')
            ->from(QuestPrerequisite::class, 'p')->where('p.task IN (:ids)')->setParameter('ids', $taskIds)->getQuery()->getArrayResult();
        return array_column($rows, 'requiredId', 'taskId');
    }

    /** Minimal assigned-target projection; never includes an outline or task count. */
    public function programs(string $userId): array
    {
        $paths = $this->em->createQueryBuilder()->select('path.id', 'path.title')->from(Enrollment::class, 'e')
            ->join('e.learningPath', 'path')->where('e.userId = :user')->setParameter('user', $userId)
            ->orderBy('e.id', 'ASC')->getQuery()->getArrayResult();
        $courses = $this->em->createQueryBuilder()->select('course.id', 'course.title')->from(Enrollment::class, 'e')
            ->join('e.course', 'course')->where('e.userId = :user')->setParameter('user', $userId)
            ->orderBy('e.id', 'ASC')->getQuery()->getArrayResult();
        $paths=array_values(array_filter($paths,fn($row)=>$this->access->pathVisible($userId,$this->em->find(\App\Entity\LearningPath::class,$row['id']))));
        $courses=array_values(array_filter($courses,fn($row)=>$this->access->can($userId,'course.view',$this->em->find(\App\Entity\Course::class,$row['id']))));
        return ['learningPaths' => $paths, 'courses' => $courses];
    }

    /** Called only with IDs already allowed by QuestAvailabilityService. */
    public function chaptersForVisibleTasks(array $taskIds): array
    {
        if (!$taskIds) { return []; }
        $rows = $this->em->createQueryBuilder()
            ->select('task.id AS taskId', 'sprint.id AS chapterId', 'sprint.title AS chapterTitle', 'course.title AS courseTitle')
            ->from(Task::class, 'task')->join('task.card', 'card')->join('card.sprint', 'sprint')->join('sprint.course', 'course')
            ->where('task.id IN (:ids)')->setParameter('ids', $taskIds)->getQuery()->getArrayResult();
        return array_column($rows, null, 'taskId');
    }
}
