<?php
declare(strict_types=1);
namespace App\Access\Infrastructure\Http;
use App\Access\Application\Service\{AccessChecker,GrantManager};
use App\Access\Domain\PermissionCatalog;
use App\Access\Domain\Entity\PermissionGrant;
use App\Learning\Domain\Entity\Course;
use App\Learning\Domain\Entity\LearningPath;
use App\Auth\Infrastructure\Session\CurrentUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\{TextType,ChoiceType};
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

final class AccessController extends AbstractController
{
    #[Route('/manage/access',name:'access_index',methods:['GET','POST'])]
    public function index(Request $request,EntityManagerInterface $em,AccessChecker $access,GrantManager $manager,CurrentUser $user): Response
    {
        $resources=['Всё приложение'=>'global'];
        foreach([Course::class=>'course',LearningPath::class=>'learning_path'] as $class=>$prefix)
            foreach($em->getRepository($class)->findAll() as $item)
                if($access->can($user->id(),'course.view',$item)||$access->can($user->id(),'course.edit',$item))$resources[$prefix.' #'.$item->id.' · '.$item->title]=$prefix.':'.$item->id;
        $permissions=PermissionCatalog::ALL;unset($permissions['access.manage']);
        $form=$this->createFormBuilder(null,['csrf_token_id'=>'access_grant'])
            ->add('targetUser',TextType::class,['label'=>'ID пользователя','constraints'=>[new Assert\NotBlank(),new Assert\Regex('/^[a-zA-Z0-9][a-zA-Z0-9_.@-]{0,99}$/D')]])
            ->add('permission',ChoiceType::class,['label'=>'Разрешение','choices'=>array_flip($permissions)])
            ->add('resource',ChoiceType::class,['label'=>'Область действия','choices'=>$resources])->getForm()->handleRequest($request);
        $error=null;$status=200;
        if($form->isSubmitted()){
            if($form->isValid()){
                $data=$form->getData();[$scope,$id]=array_pad(explode(':',$data['resource'],2),2,null);
                $resource=$scope==='global'?null:$em->find($scope==='course'?Course::class:LearningPath::class,(int)$id);
                try{$manager->grant($user->id(),$data['targetUser'],$data['permission'],$scope,$resource);$this->addFlash('success','Разрешение назначено.');return $this->redirectToRoute('access_index',status:303);}
                catch(\InvalidArgumentException $e){$error=$e->getMessage();$status=422;}
            }else $status=422;
        }
        $response=$this->render('access/index.html.twig',['form'=>$form,'grants'=>$em->getRepository(PermissionGrant::class)->findBy([],['userId'=>'ASC','permission'=>'ASC']),
            'currentId'=>$user->id(),'error'=>$error,'catalog'=>PermissionCatalog::ALL],new Response(status:$status));
        $response->headers->set('Cache-Control','private, no-store');return $response;
    }
    #[Route('/manage/access/{id}/revoke',name:'access_revoke',requirements:['id'=>'\d+'],methods:['POST'])]
    public function revoke(PermissionGrant $grant,Request $request,CurrentUser $user,GrantManager $manager): Response
    {
        if(!$this->isCsrfTokenValid('access_revoke_'.$grant->id,$request->request->getString('_token')))throw $this->createAccessDeniedException();
        $manager->revoke($user->id(),$grant);$this->addFlash('success','Назначение отозвано.');return $this->redirectToRoute('access_index',status:303);
    }
}
