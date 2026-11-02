<?php
declare(strict_types=1);
namespace App\Tests;
use App\Access\{AccessChecker,GrantManager,PermissionCatalog};
use App\Learning\Domain\Entity\Course;
use App\Learning\Domain\Entity\Sprint;
use App\Learning\Domain\Entity\Card;
use App\Learning\Domain\Entity\Task;
use App\Mentor\Domain\Entity\Enrollment;
use App\Mentor\Domain\Entity\QuestPrerequisite;
use App\Learning\Domain\Entity\LearningPath;
use App\Learning\Domain\Entity\PathCourse;
use App\Access\Domain\Entity\PermissionGrant;
use Doctrine\ORM\Tools\SchemaTool;
use App\Access\Application\Exception\PermissionDenied;

final class PermissionAccessTest extends TestCase
{
    private Course $go;private Course $php;private Task $start;private Task $hidden;
    private function em(){return self::getContainer()->get('doctrine')->getManager();}
    private function access(): AccessChecker{return self::getContainer()->get(AccessChecker::class);}
    private function grant(string $permission,?object $resource=null,string $user='001'): void
    {
        self::getContainer()->get(GrantManager::class)->install($user,$permission,$resource instanceof LearningPath?'learning_path':($resource?'course':'global'),$resource,'phpunit');
    }
    private function prepare(): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client=self::createClient();$em=$this->em();$params=$em->getConnection()->getParams();
        self::assertSame('pdo_sqlite',$params['driver']);self::assertStringEndsWith('var/test.db',str_replace('\\','/',$params['path']));
        $tool=new SchemaTool($em);$metadata=$em->getMetadataFactory()->getAllMetadata();$tool->dropSchema($metadata);$tool->createSchema($metadata);
        foreach(['go','php'] as $code){
            $course=new Course();$course->title=strtoupper($code).' COURSE';$sprint=new Sprint();$sprint->course=$course;$sprint->title='Глава '.$code;
            $card=new Card();$card->sprint=$sprint;$card->title='Карточка '.$code;
            $task=new Task();$task->card=$card;$task->title='START_'.$code;
            foreach([$course,$sprint,$card,$task] as $row)$em->persist($row);$em->flush();
            if($code==='go'){$this->go=$course;$this->start=$task;}else $this->php=$course;
        }
        $this->hidden=new Task();$this->hidden->card=$this->start->card;$this->hidden->title='HIDDEN_QUEST_DO_NOT_LEAK';$this->hidden->description='HIDDEN_DESCRIPTION';$em->persist($this->hidden);$em->flush();
        $edge=new QuestPrerequisite();$edge->task=$this->hidden;$edge->requiredTask=$this->start;$em->persist($edge);
        $enrollment=new Enrollment();$enrollment->course=$this->go;$em->persist($enrollment);$em->flush();\App\Tests\Support\DemoSession::attach($client); return $client;
    }
    private function learner(): void{foreach(['course.view','task.view','task.submit'] as $permission)$this->grant($permission,$this->go);}
    public function testNewUsersHaveNoImplicitPermissions(): void
    {
        $client=$this->prepare();foreach(array_keys(PermissionCatalog::ALL) as $permission)self::assertFalse($this->access()->can('new-person',$permission,$this->go));
        $client->request('GET','/api/courses?userId=admin&permission=course.view');self::assertResponseIsSuccessful();self::assertSame([],json_decode($client->getResponse()->getContent(),true,512,JSON_THROW_ON_ERROR));
        foreach(['/courses/'.$this->go->id,'/manage/course/'.$this->go->id.'/edit'] as $url){$client->request('GET',$url);self::assertResponseStatusCodeSame(404);}
        $client->request('GET','/');self::assertResponseStatusCodeSame(403);
    }
    public function testGoOnlyDoesNotRevealPhpViaHtmlOrJson(): void
    {
        $client=$this->prepare();$this->learner();self::assertTrue($this->access()->can('001','course.view',$this->go));self::assertFalse($this->access()->can('001','course.view',$this->php));
        foreach(['/courses','/api/courses'] as $url){$client->request('GET',$url);self::assertResponseIsSuccessful();self::assertStringContainsString('GO COURSE',$client->getResponse()->getContent());self::assertStringNotContainsString('PHP COURSE',$client->getResponse()->getContent());}
        foreach(['/courses/'.$this->php->id,'/api/courses/'.$this->php->id,'/api/courses/'.$this->php->id.'/sprints','/sprints/2','/cards/2','/api/cards/2/tasks'] as $url){$client->request('GET',$url);self::assertResponseStatusCodeSame(404);}
    }
    public function testHiddenQuestAbsentFromEveryReadProjection(): void
    {
        $client=$this->prepare();$this->learner();
        foreach(['/learn','/api/me/quests','/cards/1','/api/cards/1/tasks'] as $url){$client->request('GET',$url);self::assertResponseIsSuccessful();self::assertStringNotContainsString('HIDDEN_',$client->getResponse()->getContent());}
        $client->request('GET','/api/courses/1/progress');self::assertSame(1,json_decode($client->getResponse()->getContent(),true,512,JSON_THROW_ON_ERROR)['total']);
        $client->request('POST','/learn/quests/'.$this->hidden->id.'/complete',['_token'=>'fake']);self::assertResponseStatusCodeSame(404);
    }
    public function testPermissionDoesNotReplaceEnrollment(): void
    {
        $client=$this->prepare();$this->learner();$this->em()->getConnection()->executeStatement('DELETE FROM enrollment');
        $client->request('GET','/api/me/quests');self::assertSame([],json_decode($client->getResponse()->getContent(),true,512,JSON_THROW_ON_ERROR));
        $client->request('GET','/courses/1');self::assertResponseStatusCodeSame(404);
    }
    public function testEditOneCourseDoesNotGrantStatisticsOrGlobalCreation(): void
    {
        $client=$this->prepare();$this->grant('course.view',$this->go);$this->grant('course.edit',$this->go);
        $client->request('GET','/manage/course/1/edit');self::assertResponseIsSuccessful();
        $client->request('GET','/manage/course/2/edit');self::assertResponseStatusCodeSame(404);
        $client->request('GET','/');self::assertResponseStatusCodeSame(403);
        $client->request('GET','/manage/course/new');self::assertResponseStatusCodeSame(403);
        $this->grant('statistics.view');$client->request('GET','/');self::assertResponseIsSuccessful();
    }
    public function testExplicitPathInheritanceDoesNotSpreadThroughSharedCourse(): void
    {
        $this->prepare();$a=new LearningPath();$a->title='A';$a->slug='a';$b=new LearningPath();$b->title='B';$b->slug='b';
        foreach([$a,$b] as $path)$this->em()->persist($path);$this->em()->flush();
        foreach([[$a,$this->go],[$b,$this->go],[$b,$this->php]] as [$path,$course]){$link=new PathCourse($path);$link->course=$course;$this->em()->persist($link);}$this->em()->flush();
        $this->grant('course.edit',$a);self::assertTrue($this->access()->can('001','course.edit',$this->go));self::assertFalse($this->access()->can('001','course.edit',$this->php));self::assertFalse($this->access()->can('001','course.edit',$b));
        $this->go->title='Теперь произвольное название';$this->em()->flush();self::assertTrue($this->access()->can('001','course.edit',$this->go));
    }
    public function testManagementIsDeniedWithoutPermission(): void
    {
        $client=$this->prepare();$client->request('GET','/manage/access');self::assertResponseStatusCodeSame(403);
        $client->request('POST','/manage/access',['targetUser'=>'001','permission'=>'access.manage']);self::assertResponseStatusCodeSame(403);
    }
    public function testSelfGrantIsForbiddenEvenForAccessManager(): void
    {
        $client=$this->prepare();$this->grant('access.manage');$this->grant('course.view');
        $crawler=$client->request('GET','/manage/access');self::assertResponseIsSuccessful();
        $client->submit($crawler->selectButton('Выдать разрешение')->form(['form[targetUser]'=>'001','form[permission]'=>'course.view','form[resource]'=>'global']));self::assertResponseStatusCodeSame(403);
    }
    public function testManagerCanGrantAndRevokeAnotherUserButNotDelegateAccessManage(): void
    {
        $client=$this->prepare();$this->grant('access.manage');$this->grant('course.view');
        $crawler=$client->request('GET','/manage/access');$form=$crawler->selectButton('Выдать разрешение')->form(['form[targetUser]'=>'other-person','form[permission]'=>'course.view','form[resource]'=>'course:1']);
        $values=$form->getPhpValues();$client->submit($form);self::assertResponseRedirects('/manage/access',303);self::assertTrue($this->access()->can('other-person','course.view',$this->go));
        $values['form']['permission']='access.manage';$client->request('POST','/manage/access',$values);self::assertResponseStatusCodeSame(422);self::assertFalse($this->access()->can('other-person','access.manage'));
        $crawler=$client->request('GET','/manage/access');$client->submit($crawler->filter('form[action$="/revoke"]')->first()->form());self::assertResponseRedirects();self::assertFalse($this->access()->can('other-person','course.view',$this->go));
    }
    public function testScopedEditorFormChoicesDoNotExposeOtherCourses(): void
    {
        $client=$this->prepare();$this->grant('course.view',$this->go);$this->grant('course.edit',$this->go);
        foreach(['/mentor/enrollments/new','/mentor/prerequisites/new'] as $url){$client->request('GET',$url);self::assertResponseIsSuccessful();self::assertStringNotContainsString('PHP COURSE',$client->getResponse()->getContent());self::assertStringNotContainsString('START_php',$client->getResponse()->getContent());}
    }
    public function testDiagnosticPermissionsRemainSeparateAndCannotBeForged(): void
    {
        $client=$this->prepare();$client->request('GET','/api/me/assessments?permission=assessment.take&userId=001');self::assertResponseStatusCodeSame(403);
        $this->grant('assessment.take');$client->request('GET','/api/me/assessments');self::assertResponseIsSuccessful();
        $client->request('GET','/api/me/assessment-attempts/'.str_repeat('a',32).'/result');self::assertResponseStatusCodeSame(403);
    }
    public function testCannotDelegateBeyondOwnScope(): void
    {
        $this->prepare();$this->grant('access.manage');$this->grant('course.edit',$this->go);$this->expectException(PermissionDenied::class);
        self::getContainer()->get(GrantManager::class)->grant('001','other-person','course.edit','course',$this->php);
    }
    public function testManagementCsrfPreventsWrites(): void
    {
        $client=$this->prepare();$this->grant('access.manage');$this->grant('course.view');
        $client->request('POST','/manage/access',['form'=>['targetUser'=>'other-person','permission'=>'course.view','resource'=>'global']]);self::assertResponseStatusCodeSame(422);
        self::assertSame(0,$this->em()->getRepository(PermissionGrant::class)->count(['userId'=>'other-person']));
    }
    public function testExplicitBootstrapIsIdempotentAndDoesNotCreateAccessManager(): void
    {
        $this->prepare();$application=new \Symfony\Bundle\FrameworkBundle\Console\Application(self::$kernel);
        $tester=new \Symfony\Component\Console\Tester\CommandTester($application->find('app:access:bootstrap'));
        $tester->execute(['userId'=>'explicit-demo']);self::assertSame(0,$tester->getStatusCode());
        self::assertTrue($this->access()->can('explicit-demo','course.create'));self::assertFalse($this->access()->can('explicit-demo','access.manage'));
        $count=$this->em()->getRepository(PermissionGrant::class)->count([]);$tester->execute(['userId'=>'explicit-demo']);self::assertSame($count,$this->em()->getRepository(PermissionGrant::class)->count([]));
        self::assertFalse($this->access()->can('unrelated-person','course.view',$this->go));
    }
    public function testEveryHttpActionHasExplicitPolicyRegistration(): void
    {
        $this->prepare();$count=0;
        foreach(self::getContainer()->get('router')->getRouteCollection() as $route){
            $controller=$route->getDefault('_controller');if(!is_string($controller)||!str_starts_with($controller,'App\\'))continue;
            [$class,$method]=explode('::',$controller,2);$short=(new \ReflectionClass($class))->getShortName();
            self::assertContains($method,\App\Access\AccessSubscriber::METHODS[$short]??[],$controller);++$count;
        }self::assertGreaterThan(40,$count);
    }
    public function testInvalidScopeForGlobalOnlyPermissionRejected(): void
    {
        $this->prepare();$this->expectException(\InvalidArgumentException::class);
        self::getContainer()->get(GrantManager::class)->install('other-person','statistics.view','course',$this->go,'phpunit');
    }
    public function testViewDoesNotImplySubmit(): void
    {
        $client=$this->prepare();$this->grant('course.view',$this->go);$this->grant('task.view',$this->go);
        $client->request('GET','/api/me/quests');self::assertResponseIsSuccessful();self::assertFalse(json_decode($client->getResponse()->getContent(),true)[0]['canSubmit']);
        $client->request('POST','/learn/quests/'.$this->start->id.'/start',['_token'=>'fake']);self::assertResponseStatusCodeSame(404);
    }
}
