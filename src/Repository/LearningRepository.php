<?php
namespace App\Repository;
use App\Entity\{Course, Sprint, Card, Task, TaskProgress};
use Doctrine\ORM\EntityManagerInterface;
class LearningRepository
{
    public function __construct(private EntityManagerInterface $em, private \App\Access\ResourcePolicy $policy, private \App\Service\CurrentUser $user) {}
    public function courses(): array { return array_values(array_filter($this->em->getRepository(Course::class)->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC']), fn($course)=>$this->policy->courseVisible($this->user->id(),$course))); }
    public function sprints(Course $course): array { return array_values(array_filter($this->em->getRepository(Sprint::class)->findBy(['course' => $course], ['position' => 'ASC', 'id' => 'ASC']),fn($item)=>$this->policy->containerVisible($this->user->id(),$item))); }
    public function cards(Sprint $sprint): array { return array_values(array_filter($this->em->getRepository(Card::class)->findBy(['sprint' => $sprint], ['position' => 'ASC', 'id' => 'ASC']),fn($item)=>$this->policy->containerVisible($this->user->id(),$item))); }
    public function tasks(Card $card): array { return array_values(array_filter($this->em->getRepository(Task::class)->findBy(['card' => $card], ['position' => 'ASC', 'id' => 'ASC']), fn($task)=>$this->policy->taskVisible($this->user->id(),$task))); }
    public function statistics(): array {
        $users=$this->em->getConnection()->fetchFirstColumn('SELECT id AS user_id FROM demo_account UNION SELECT user_id FROM permission_grant UNION SELECT user_id FROM enrollment UNION SELECT user_id FROM task_progress UNION SELECT user_id FROM entry_attempt UNION SELECT user_id FROM coding_submission');
        return ['knownUsers'=>count($users),'courses' => $this->em->getRepository(Course::class)->count([]), 'sprints' => $this->em->getRepository(Sprint::class)->count([]), 'tasks' => $this->em->getRepository(Task::class)->count([])];
    }
    public function courseCounts(Course $course, string $userId): array {
        $base = $this->em->createQueryBuilder()->from(Task::class, 't')->join('t.card', 'c')->join('c.sprint', 's')->where('s.course = :course')->setParameter('course', $course);
        $total=0;$completed=0;
        foreach($base->select('t')->getQuery()->getResult() as $task){
            if(!$this->policy->taskVisible($userId,$task))continue;
            ++$total;if($this->completion($task,$userId))++$completed;
        }
        return [$total,$completed];
    }
    public function completedIds(Card $card, string $userId): array {
        $rows = $this->em->createQueryBuilder()->select('IDENTITY(p.task) AS taskId')->from(TaskProgress::class, 'p')->join('p.task', 't')->where('t.card = :card AND p.userId = :user')->setParameter('card', $card)->setParameter('user', $userId)->getQuery()->getArrayResult();
        return array_values(array_filter(array_map('intval', array_column($rows, 'taskId')),fn($id)=>$this->policy->taskVisible($userId,$this->em->find(Task::class,$id))));
    }
    public function completion(Task $task, string $userId): ?TaskProgress { return $this->em->getRepository(TaskProgress::class)->findOneBy(['task' => $task, 'userId' => $userId]); }
}
