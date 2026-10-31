<?php
namespace App\Service;
// Local-only identity provider. Never read userId from query, headers or cookies here.
// This is a demo seam for future authorization, not an authentication boundary.
class CurrentUser
{
    public function __construct(#[\Symfony\Component\DependencyInjection\Attribute\Autowire('%env(default:local_demo_user:COURSE_DEMO_USER_ID)%')] private string $userId,
        private \Symfony\Component\HttpFoundation\RequestStack $requests,private \Doctrine\ORM\EntityManagerInterface $em) {}
    public function id(): string
    {
        $request=$this->requests->getCurrentRequest();
        // Trusted CLI context only; an HTTP request never uses the configured fallback.
        if(!$request)return $this->userId;
        if(!$request->hasSession())return '';
        $id=$request->getSession()->get('demo_account_id');
        if(!is_string($id)||$id==='')return '';
        $account=$this->em->find(\App\Entity\DemoAccount::class,$id);
        return $account?->enabled?$account->id:'';
    }
}
