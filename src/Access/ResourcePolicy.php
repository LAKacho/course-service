<?php
declare(strict_types=1);
namespace App\Access;
use App\Entity\{Course,Task,QuestState,Enrollment,PathCourse};
use App\Service\QuestAvailabilityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
final class ResourcePolicy
{
    public function __construct(private AccessChecker $access,private QuestAvailabilityService $availability,private EntityManagerInterface $em) {}
    public function enrolled(string $user,Course $course): bool
    {
        if($this->em->getRepository(Enrollment::class)->count(['userId'=>$user,'course'=>$course])) return true;
        foreach($this->em->getRepository(PathCourse::class)->findBy(['course'=>$course]) as $link)
            if($this->em->getRepository(Enrollment::class)->count(['userId'=>$user,'learningPath'=>$link->path])) return true;
        return false;
    }
    public function courseVisible(string $user,Course $course): bool
    {
        return $this->access->can($user,'course.view',$course) && ($this->access->manages($user,$course) || $this->enrolled($user,$course));
    }
    public function taskVisible(string $user,Task $task,bool $learner=false): bool
    {
        return $this->access->can($user,'task.view',$task)
            && (!$learner && $this->access->manages($user,$task) || $this->availability->state($task,$user)!==QuestState::Locked);
    }
    public function learner(string $user,Task $task,string $action): void
    {
        if(!$this->access->can($user,$action,$task) || !$this->taskVisible($user,$task,true)) throw new NotFoundHttpException('Задание не найдено.');
    }
    public function canSubmit(string $user,Task $task): bool
    {
        return $this->access->can($user,'task.submit',$task) && $this->taskVisible($user,$task,true);
    }
    public function containerVisible(string $user,object $resource): bool
    {
        $course=$this->access->course($resource);
        if(!$course || !$this->courseVisible($user,$course))return false;
        if($resource instanceof Course || $this->access->manages($user,$resource))return true;
        $qb=$this->em->createQueryBuilder()->select('t')->from(Task::class,'t')->join('t.card','card');
        if($resource instanceof \App\Entity\Card)$qb->where('t.card = :resource');
        elseif($resource instanceof \App\Entity\Sprint)$qb->where('card.sprint = :resource');else return false;
        foreach($qb->setParameter('resource',$resource)->getQuery()->getResult() as $task)if($this->taskVisible($user,$task,true))return true;
        return false;
    }
}
