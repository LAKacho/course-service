<?php
declare(strict_types=1);
namespace App\Auth\Infrastructure\Http;
use App\Auth\Infrastructure\Session\CurrentUser;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\{JsonResponse,RedirectResponse};
final class LoginSubscriber implements EventSubscriberInterface
{
    public function __construct(private CurrentUser $user){}
    public static function getSubscribedEvents():array{return ['kernel.request'=>['check',0]];}
    public function check(RequestEvent $event):void
    {
        $request=$event->getRequest();$route=$request->attributes->get('_route');
        if(!$route||in_array($route,['demo_login','demo_logout'],true)||$this->user->id()!=='')return;
        if(str_starts_with($request->getPathInfo(),'/api/')){
            $event->setResponse(new JsonResponse(['error'=>['status'=>401,'message'=>'Для продолжения войдите в систему.']],401,['Cache-Control'=>'private, no-store']));return;
        }
        if($request->isMethod('GET') && strlen($request->getPathInfo())<2048)$request->getSession()->set('demo_target',$request->getPathInfo());
        $event->setResponse(new RedirectResponse('/login',303,['Cache-Control'=>'private, no-store']));
    }
}
