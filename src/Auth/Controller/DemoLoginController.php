<?php
declare(strict_types=1);
namespace App\Auth\Controller;
use App\Auth\Domain\Entity\DemoAccount;
use App\Auth\Infrastructure\Session\CurrentUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

final class DemoLoginController extends AbstractController
{
    #[Route('/login',name:'demo_login',methods:['GET','POST'])]
    public function login(Request $request,EntityManagerInterface $em,CurrentUser $user,\App\Access\Application\Service\AccessChecker $access): Response
    {
        if($user->id()!=='')return $this->redirectToRoute('student_home',status:303);
        $form=$this->createFormBuilder(null,['csrf_token_id'=>'demo_login'])
            ->add('login',TextType::class,['label'=>'Р›РѕРіРёРЅ','constraints'=>[new Assert\NotBlank(),new Assert\Length(max:100)],'attr'=>['autocomplete'=>'username','autofocus'=>true]])->getForm()->handleRequest($request);
        $error=null;$status=200;
        if($form->isSubmitted()){
            if($form->isValid()){
                $login=trim($form->get('login')->getData());$account=$em->find(DemoAccount::class,$login);
                if($account && $account->enabled){
                    $session=$request->getSession();$session->migrate(true);$session->set('demo_account_id',$account->id);
                    $target=$session->remove('demo_target');
                    if($target==='/'&&!$access->can($account->id,'statistics.view'))$target=null;
                    $home=$access->can($account->id,'statistics.view')?'dashboard':($access->any($account->id,'course.edit')?'courses':'student_home');
                    return $this->redirect(is_string($target)&&str_starts_with($target,'/')&&!str_starts_with($target,'//')&&!str_contains($target,'\\')?$target:$this->generateUrl($home),303);
                }
                $error='РЈС‡С‘С‚РЅР°СЏ Р·Р°РїРёСЃСЊ РЅРµ РЅР°Р№РґРµРЅР° РёР»Рё РѕС‚РєР»СЋС‡РµРЅР°.';
            }
            $status=422;
        }
        $response=$this->render('auth/login.html.twig',['form'=>$form,'error'=>$error],new Response(status:$status));
        $response->headers->set('Cache-Control','private, no-store');return $response;
    }
    #[Route('/logout',name:'demo_logout',methods:['POST'])]
    public function logout(Request $request): Response
    {
        if(!$this->isCsrfTokenValid('demo_logout',$request->request->getString('_token')))throw $this->createAccessDeniedException();
        $request->getSession()->invalidate();return $this->redirectToRoute('demo_login',status:303);
    }
}

\n