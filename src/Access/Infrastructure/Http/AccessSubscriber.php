<?php
declare(strict_types=1);
namespace App\Access\Infrastructure\Http;
use App\Access\Application\Service\{AccessChecker,ResourcePolicy};
use App\Learning\Domain\Entity\Course;
use App\Learning\Domain\Entity\Sprint;
use App\Learning\Domain\Entity\Card;
use App\Learning\Domain\Entity\Task;
use App\Learning\Domain\Entity\LearningPath;
use App\Mentor\Domain\Entity\QuestPrerequisite;
use App\Coding\Domain\Entity\CodeSubmission;
use App\Auth\Infrastructure\Session\CurrentUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException,NotFoundHttpException};

/** Every application HTTP controller passes through one fail-closed policy registry. */
final class AccessSubscriber implements EventSubscriberInterface
{
    public const METHODS=[
        'DemoLoginController'=>['login','logout'],
        'AccessController'=>['index','revoke'],
        'AssessmentController'=>['index','start','attempt','answer','finish','result'],
        'AssessmentApiController'=>['index','attempt','result'],
        'CodingController'=>['task','refresh','create'],
        'StudentController'=>['home','start','complete'],
        'StudentApiController'=>['programs','quests','progress'],
        'LearningController'=>['dashboard','courses','course','sprint','card','create','edit','status','complete'],
        'ApiController'=>['courses','course','sprints','cards','tasks','progress'],
        'LearningPathController'=>['index','create','edit','show','addCourse','position'],
        'LearningPathApiController'=>['index','show','courses'],
        'MentorProgramController'=>['index','show'],
        'MentorLearningController'=>['index','enroll','prerequisite','edit'],
    ];
    public function __construct(private AccessChecker $access,private ResourcePolicy $policy,private CurrentUser $current,private EntityManagerInterface $em) {}
    public static function getSubscribedEvents(): array{return ['kernel.controller_arguments'=>'check'];}
    public function check(ControllerArgumentsEvent $event): void
    {
        $controller=$event->getController();if(!is_array($controller))return;
        [$object,$method]=$controller;$class=$object::class;
        if(!str_starts_with($class,'App\\') || !str_contains($class,'Controller'))return;
        $user=$this->current->id();$request=$event->getRequest();$args=$event->getArguments();
        $resource=null;foreach($args as $arg)if($arg instanceof Course||$arg instanceof Sprint||$arg instanceof Card||$arg instanceof Task||$arg instanceof LearningPath||$arg instanceof QuestPrerequisite){$resource=$arg;break;}
        $short=(new \ReflectionClass($object))->getShortName();
        if(!in_array($method,self::METHODS[$short]??[],true))throw new AccessDeniedHttpException('Политика действия не определена.');
        switch($short){
            case 'DemoLoginController':return;
            case 'AccessController':$this->access->require($user,'access.manage');return;
            case 'AssessmentController':case 'AssessmentApiController':
                $this->access->require($user,$method==='result'?'assessment.results.view':'assessment.take');return;
            case 'CodingController':
                if($method==='create'){$this->access->require($user,'course.edit',$resource);return;}
                if($method==='refresh'){
                    $submission=$this->em->getRepository(CodeSubmission::class)->findOneBy(['publicId'=>$request->attributes->get('id'),'userId'=>$user]);
                    if(!$submission)throw new NotFoundHttpException('Решение не найдено.');$resource=$submission->exercise->task;
                }
                $this->policy->learner($user,$resource,$request->isMethod('POST')?'task.submit':'task.view');return;
            case 'StudentApiController':return; // list projections are filtered, absence yields empty arrays
            case 'StudentController':
                if($method!=='home')$this->policy->learner($user,$resource,'task.submit');return;
            case 'LearningController':
                if($method==='dashboard'){$this->access->require($user,'statistics.view');return;}
                if($method==='courses')return;
                if($method==='create'){
                    $kind=$request->attributes->get('kind');if($kind==='course'){$this->access->require($user,'course.create');return;}
                    $type=['sprint'=>Course::class,'card'=>Sprint::class,'task'=>Card::class][$kind];
                    $parent=$this->em->find($type,(int)$request->attributes->get('parentId'))??throw new NotFoundHttpException();
                    $this->access->require($user,'course.edit',$parent);return;
                }
                if($method==='edit'){
                    $type=['course'=>Course::class,'sprint'=>Sprint::class,'card'=>Card::class,'task'=>Task::class][$request->attributes->get('kind')];
                    $resource=$this->em->find($type,(int)$request->attributes->get('id'))??throw new NotFoundHttpException();
                }
                if(in_array($method,['edit','status'],true)){$this->access->require($user,'course.edit',$resource);return;}
                if($method==='complete'){
                    $this->access->require($user,'task.submit',$resource);
                    if(!$this->access->manages($user,$resource))$this->policy->learner($user,$resource,'task.submit');return;
                }
                break;
            case 'ApiController':if($method==='courses')return;break;
            case 'LearningPathController':case 'LearningPathApiController':
                if(in_array($method,['index'],true))return;
                if($method==='create'){$this->access->require($user,'course.create');return;}
                if(in_array($method,['edit','addCourse','position'],true)){$this->access->require($user,'course.edit',$resource);return;}
                if($resource instanceof LearningPath && $this->access->pathVisible($user,$resource))return;
                throw new NotFoundHttpException('Направление не найдено.');
            case 'MentorProgramController':
                if($method==='index'){$this->requireAnyEditor($user);return;}
                if($this->access->can($user,'course.edit',$resource))return;
                foreach($this->em->getRepository(\App\Learning\Domain\Entity\PathCourse::class)->findBy(['path'=>$resource]) as $link)if($this->access->can($user,'course.edit',$link->course))return;
                throw new NotFoundHttpException();
            case 'MentorLearningController':
                $this->requireAnyEditor($user);
                if($resource instanceof QuestPrerequisite){$this->access->require($user,'course.edit',$resource->task);$this->access->require($user,'course.edit',$resource->requiredTask);}return;
            default:throw new AccessDeniedHttpException('Политика контроллера не определена.');
        }
        if(!$resource || !$this->policy->containerVisible($user,$resource))throw new NotFoundHttpException('Ресурс не найден.');
    }
    private function requireAnyEditor(string $user): void
    {
        if($this->access->can($user,'course.edit'))return;
        foreach($this->em->getRepository(Course::class)->findAll() as $course)if($this->access->can($user,'course.edit',$course))return;
        throw new AccessDeniedHttpException('Недостаточно разрешений.');
    }
}
