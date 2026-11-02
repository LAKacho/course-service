<?php
namespace App\Tests\Support;
use App\Auth\Domain\Entity\DemoAccount;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
final class DemoSession extends KernelTestCase
{
    public static function attach(KernelBrowser $client,string $id='001'):void
    {
        $em=self::getContainer()->get('doctrine')->getManager();
        if(!$em->find(DemoAccount::class,$id)){$account=new DemoAccount();$account->id=$id;$account->displayName=$id;$em->persist($account);$em->flush();}
        $session=self::getContainer()->get('session.factory')->createSession();$session->set('demo_account_id',$id);$session->save();
        $client->getCookieJar()->set(new Cookie($session->getName(),$session->getId()));
    }
}
