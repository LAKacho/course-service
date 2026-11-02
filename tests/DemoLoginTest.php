<?php
declare(strict_types=1);
namespace App\Tests;
use App\Auth\Domain\Entity\DemoAccount;
use App\Access\Domain\Entity\PermissionGrant;
use Doctrine\ORM\Tools\SchemaTool;
final class DemoLoginTest extends TestCase
{
    private function em(){return self::getContainer()->get('doctrine')->getManager();}
    private function prepare(){
        $client=self::createClient();$em=$this->em();self::assertSame('pdo_sqlite',$em->getConnection()->getParams()['driver']);
        $schema=new SchemaTool($em);$metadata=$em->getMetadataFactory()->getAllMetadata();$schema->dropSchema($metadata);$schema->createSchema($metadata);
        foreach(['alice','bob','disabled'] as $id){$a=new DemoAccount();$a->id=$id;$a->displayName=$id;$a->enabled=$id!=='disabled';$em->persist($a);}$em->flush();return $client;
    }
    private function login($client,string $id='alice'):void{$c=$client->request('GET','/login');$client->submit($c->selectButton('Войти')->form(['form[login]'=>$id]));}
    public function testAnonymousHtmlRedirectsAndApiReturns401():void
    {
        $c=$this->prepare();$c->request('GET','/learn?userId=alice');self::assertResponseRedirects('/login',303);
        $c->request('GET','/api/me/quests?userId=alice');self::assertResponseStatusCodeSame(401);
    }
    public function testLoginDoesNotGrantRightsAndLogoutInvalidatesSession():void
    {
        $c=$this->prepare();$this->login($c);self::assertResponseRedirects('/learn',303);$page=$c->followRedirect();self::assertSelectorExists('body[data-current-user="alice"]');
        self::assertSame(0,$this->em()->getRepository(PermissionGrant::class)->count([]));$c->request('GET','/manage/access');self::assertResponseStatusCodeSame(403);
        $page=$c->request('GET','/learn');$c->submit($page->selectButton('Выйти')->form());self::assertResponseRedirects('/login',303);
        $c->request('GET','/api/me/quests');self::assertResponseStatusCodeSame(401);
    }
    public function testUnknownAndDisabledLoginRejected():void
    {
        $c=$this->prepare();foreach(['unknown','disabled'] as $id){$this->login($c,$id);self::assertResponseStatusCodeSame(422);}
        self::assertSame(3,$this->em()->getRepository(DemoAccount::class)->count([]));
    }
    public function testCsrfAndPermissionForgeryRejected():void
    {
        $c=$this->prepare();$c->request('POST','/login',['form'=>['login'=>'alice'],'userId'=>'bob','permission'=>'access.manage']);self::assertResponseStatusCodeSame(422);
        $c->request('GET','/api/me/quests');self::assertResponseStatusCodeSame(401);
        $this->login($c);$c->request('POST','/logout');self::assertResponseStatusCodeSame(403);
    }
    public function testDisablingAccountRevokesSession():void
    {
        $c=$this->prepare();$this->login($c);$account=$this->em()->find(DemoAccount::class,'alice');$account->enabled=false;$this->em()->flush();
        $c->request('GET','/api/me/quests');self::assertResponseStatusCodeSame(401);
    }
}
