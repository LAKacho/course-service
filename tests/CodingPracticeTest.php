<?php
declare(strict_types=1);
namespace App\Tests;
use App\Coding\Domain\Entity\{ExerciseVersion, CodeSubmission};
use App\Coding\Service\{ExerciseService, SubmissionService, SubmissionTransitions, CodingView};
use App\Coding\Contract\RunnerResult;
use App\Learning\Domain\Entity\Course;
use App\Learning\Domain\Entity\Sprint;
use App\Learning\Domain\Entity\Card;
use App\Learning\Domain\Entity\Task;
use App\Mentor\Domain\Entity\Enrollment;
use App\Learning\Domain\Entity\TaskProgress;
use App\Mentor\Domain\Entity\QuestPrerequisite;
use App\Service\{QuestAvailabilityService, ProgressService};
use App\Tests\Support\MockCodeRunnerClient;
use Doctrine\ORM\Tools\SchemaTool;
use App\Shared\Application\Exception\{Conflict,ResourceNotVisible};

final class CodingPracticeTest extends TestCase
{
    private Task $task;
    private ExerciseVersion $version;
    private function em() { return self::getContainer()->get('doctrine')->getManager(); }
    private function prepare(): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client = self::createClient(); $em = $this->em();
        self::assertSame('pdo_sqlite', $em->getConnection()->getParams()['driver']);
        self::assertStringEndsWith('var/test.db', str_replace('\\', '/', $em->getConnection()->getParams()['path']));
        $schema = new SchemaTool($em); $metadata = $em->getMetadataFactory()->getAllMetadata(); $schema->dropSchema($metadata); $schema->createSchema($metadata);
        \App\Tests\Support\LegacyAccessFixture::install($em);
        $course = new Course(); $course->title = 'Практикум'; $sprint = new Sprint(); $sprint->course = $course;
        $card = new Card(); $card->sprint = $sprint; $task = new Task(); $task->card = $card; $task->title = 'Сумма';
        $enrollment = new Enrollment(); $enrollment->course = $course;
        foreach ([$course, $sprint, $card, $task, $enrollment] as $row) { $em->persist($row); } $em->flush();
        $this->task = $task;
        $this->version = self::getContainer()->get(ExerciseService::class)->createVersion($task, 'Сумма двух чисел', 'Верните сумму двух целых чисел.',
            ['php' => '<?php function solve(int $a, int $b): int { return 0; }', 'go' => 'package main\nfunc solve(a, b int) int { return 0 }'],
            [['id' => 'public_1', 'input' => [2,3], 'expected' => 5]],
            [['id' => 'SECRET_CASE', 'input' => 'SECRET_INPUT', 'expected' => 'SECRET_EXPECTED']]);
        \App\Tests\Support\DemoSession::attach($client); return $client;
    }
    private function service(MockCodeRunnerClient $runner): SubmissionService
    {
        return new SubmissionService(self::getContainer()->get(\App\Coding\Application\Port\CodingRepository::class), self::getContainer()->get(QuestAvailabilityService::class), $runner, new SubmissionTransitions(),self::getContainer()->get(\App\Access\ResourcePolicy::class));
    }
    private function save(SubmissionService $service, string $mode = 'submit', string $language = 'php'): CodeSubmission
    {
        return $service->save($this->task, '001', $this->version->id, $language, 'user source', $mode);
    }
    private function runnerResult(CodeSubmission $submission, string $status = 'passed', ?bool $all = true): RunnerResult
    {
        return new RunnerResult($submission->runnerJobId, $submission->publicId, $submission->exercise->testsVersion, $status,
            [['id' => 'public_1', 'passed' => $status === 'passed', 'stdout' => 'SECRET_INPUT']], $all);
    }
    public function testCreationAndImmutableVersions(): void
    {
        $this->prepare(); self::assertSame('coding', $this->task->type);
        $service = $this->service(new MockCodeRunnerClient()); $submission = $this->save($service, 'draft');
        $v2 = self::getContainer()->get(ExerciseService::class)->createVersion($this->task, 'Новая версия', 'Новое условие', ['php' => 'new source'],
            [['id'=>'new_public','input'=>1,'expected'=>2]], [['id'=>'new_hidden','input'=>3,'expected'=>4]]);
        self::assertSame(2, $v2->version); self::assertSame(1, $submission->exercise->version);
        self::assertSame('Сумма двух чисел', $submission->exercise->title); self::assertNotSame($v2->testsVersion, $submission->exercise->testsVersion);
    }
    public function testPhpGoAndRepeatedAttemptsPreserveSource(): void
    {
        $this->prepare(); $service = $this->service(new MockCodeRunnerClient());
        $a = $this->save($service, 'draft'); $b = $this->save($service, 'draft', 'go'); $c = $this->save($service, 'draft');
        self::assertNotSame($a->publicId, $b->publicId); self::assertNotSame($a->publicId, $c->publicId);
        self::assertSame('go', $b->language); self::assertSame('user source', $a->sourceCode);
        self::assertSame(3, $this->em()->getRepository(CodeSubmission::class)->count([]));
    }
    public function testInvalidLanguageCodeAndModeCreateNoSubmissions(): void
    {
        $this->prepare(); $service = $this->service(new MockCodeRunnerClient());
        foreach ([['python','source','draft'],['php','   ','draft'],['php',str_repeat('x',65537),'draft'],['php','source','passed'],['php',str_repeat('я',33000),'draft']] as [$language,$source,$mode]) {
            try { $service->save($this->task,'001',$this->version->id,$language,$source,$mode); self::fail('Invalid input accepted'); }
            catch (\InvalidArgumentException) { self::assertSame(0, $this->em()->getRepository(CodeSubmission::class)->count([])); }
        }
    }
    public function testUnavailableRunnerSavesDraftNeverCompletes(): void
    {
        $this->prepare(); $runner = new MockCodeRunnerClient(); $runner->online = false; $service = $this->service($runner);
        foreach (['check','submit'] as $mode) { $saved = $this->save($service, $mode); self::assertSame('draft', $saved->status); self::assertNull($saved->score); }
        self::assertCount(0, $runner->requests); self::assertSame(0, $this->em()->getRepository(TaskProgress::class)->count([]));
    }
    public function testCheckContainsOnlyPublicTestsAndNeverCompletes(): void
    {
        $this->prepare(); $runner = new MockCodeRunnerClient(); $service = $this->service($runner); $saved = $this->save($service, 'check');
        self::assertSame('queued', $saved->status); self::assertCount(1, $runner->requests[0]->tests);
        self::assertStringNotContainsString('SECRET', json_encode($runner->requests[0]->tests));
        $runner->result = $this->runnerResult($saved); $service->refresh($saved, '001');
        self::assertSame('passed', $saved->status); self::assertSame(0, $this->em()->getRepository(TaskProgress::class)->count([]));
    }
    public function testTrustedFinalPassIsIdempotentAndUnlocksNextQuest(): void
    {
        $this->prepare(); $next = new Task(); $next->card = $this->task->card; $next->title = 'Следующий'; $this->em()->persist($next);
        $dependency = new QuestPrerequisite(); $dependency->task = $next; $dependency->requiredTask = $this->task; $this->em()->persist($dependency); $this->em()->flush();
        $runner = new MockCodeRunnerClient(); $service = $this->service($runner); $saved = $this->save($service);
        self::assertCount(2, $runner->requests[0]->tests); self::assertSame('locked', self::getContainer()->get(QuestAvailabilityService::class)->state($next,'001')->value);
        $result = $this->runnerResult($saved); $service->acceptTrustedResult($saved,$result); $service->acceptTrustedResult($saved,$result);
        self::assertSame('passed', $saved->status); self::assertSame(100.0,$saved->score);
        self::assertSame(1, $this->em()->getRepository(TaskProgress::class)->count([]));
        self::assertSame('available', self::getContainer()->get(QuestAvailabilityService::class)->state($next,'001')->value);
        self::assertStringNotContainsString('SECRET', json_encode(self::getContainer()->get(CodingView::class)->submission($saved)));
    }
    public function testRunningFailedErrorTimeoutDoNotComplete(): void
    {
        $this->prepare(); $runner = new MockCodeRunnerClient(); $service = $this->service($runner);
        foreach (['failed','error','timeout'] as $status) {
            $saved = $this->save($service); $service->acceptTrustedResult($saved,$this->runnerResult($saved,'running',null));
            self::assertSame('running',$saved->status); $service->acceptTrustedResult($saved,$this->runnerResult($saved,$status,false)); self::assertSame($status,$saved->status);
        }
        self::assertSame(0,$this->em()->getRepository(TaskProgress::class)->count([]));
    }
    public function testTransportFailureIsErrorNotSuccess(): void
    {
        $this->prepare(); $runner = new MockCodeRunnerClient(); $runner->throws = true; $saved = $this->save($this->service($runner));
        self::assertSame('error',$saved->status); self::assertNull($saved->score); self::assertSame(0,$this->em()->getRepository(TaskProgress::class)->count([]));
    }
    public function testConflictingRepeatedResultRejected(): void
    {
        $this->prepare(); $service = $this->service(new MockCodeRunnerClient()); $saved = $this->save($service);
        $service->acceptTrustedResult($saved,$this->runnerResult($saved)); $this->expectException(Conflict::class);
        $service->acceptTrustedResult($saved,$this->runnerResult($saved,'failed',false));
    }
    public function testWrongRunnerIdentityCannotComplete(): void
    {
        $this->prepare(); $service = $this->service(new MockCodeRunnerClient()); $saved = $this->save($service);
        $this->expectException(\DomainException::class);
        $service->acceptTrustedResult($saved,new RunnerResult('other_job',$saved->publicId,$this->version->testsVersion,'passed',[['id'=>'public_1','passed'=>true]],true));
    }
    public function testPassWithoutAllTestsAttestationRejected(): void
    {
        $this->prepare(); $service = $this->service(new MockCodeRunnerClient()); $saved = $this->save($service);
        $this->expectException(\DomainException::class); $service->acceptTrustedResult($saved,$this->runnerResult($saved,'passed',null));
    }
    public function testManualCompletionIsBlocked(): void
    {
        $this->prepare(); $this->expectException(Conflict::class); self::getContainer()->get(ProgressService::class)->setCompleted($this->task,true);
    }
    public function testNoAssignmentCannotSeeExercise(): void
    {
        $this->prepare(); $this->expectException(ResourceNotVisible::class);
        self::getContainer()->get(SubmissionService::class)->exercise($this->task,'other-user');
    }
    public function testOwnerGuard(): void
    {
        $this->prepare(); $saved = $this->save($this->service(new MockCodeRunnerClient()),'draft');
        $this->expectException(ResourceNotVisible::class); self::getContainer()->get(SubmissionService::class)->owned($saved->publicId,'other-user');
    }
    public function testHtmlNoSecretsCsrfAndClientResultForgery(): void
    {
        $client = $this->prepare(); $id = $this->task->id;
        $crawler = $client->request('GET','/learn/coding/'.$id.'?userId=intruder'); self::assertResponseIsSuccessful();
        $html = $client->getResponse()->getContent(); self::assertStringNotContainsString('SECRET',$html); self::assertStringNotContainsString('hiddenTests',$html);
        self::assertStringContainsString('Проверка временно недоступна',$html);
        $form = $crawler->selectButton('Сохранить решение')->form(); $values = $form->getPhpValues();
        $values['code_solution']['sourceCode'] = '<?php echo "</textarea><script>alert(1)</script>";';
        $values['userId']='intruder'; $values['status']='passed'; $values['score']=100; $values['result']=['passed'=>true]; $values['action']='submit';
        $client->request('POST','/learn/coding/'.$id,$values); self::assertResponseRedirects(); $client->followRedirect(); self::assertResponseIsSuccessful();
        $saved = $this->em()->getRepository(CodeSubmission::class)->findOneBy([]);
        self::assertSame('001',$saved->userId); self::assertSame('draft',$saved->status); self::assertNull($saved->score);
        self::assertStringNotContainsString('<script>alert(1)</script>',$client->getResponse()->getContent());
        self::assertSame(0,$this->em()->getRepository(TaskProgress::class)->count([]));
        unset($values['code_solution']['_token']); $client->request('POST','/learn/coding/'.$id,$values); self::assertResponseStatusCodeSame(422);
        self::assertSame(1,$this->em()->getRepository(CodeSubmission::class)->count([]));
    }
    public function testMentorCreationAndStudentLockedRoutes(): void
    {
        $client=$this->prepare(); $id=$this->task->id;
        $crawler=$client->request('GET','/mentor/coding/'.$id.'/versions/new'); self::assertResponseIsSuccessful();
        $form=$crawler->selectButton('Создать версию')->form(); $values=$form->getPhpValues(); $values['exercise']['php']='<?php return 0;'; $values['exercise']['statement']='Верните сумму.';
        $client->request('POST','/mentor/coding/'.$id.'/versions/new',$values); self::assertResponseRedirects();
        self::assertSame(2,$this->em()->getRepository(ExerciseVersion::class)->count([]));
        $this->em()->getConnection()->executeStatement('DELETE FROM enrollment');
        $client->request('GET','/learn/coding/'.$id); self::assertResponseStatusCodeSame(404);
    }
    public function testDraftPreservesExistingOtherProgress(): void
    {
        $this->prepare(); $other=new Task(); $other->card=$this->task->card; $other->title='Прежнее'; $this->em()->persist($other); $this->em()->flush();
        $progress=new TaskProgress('001',$other); $this->em()->persist($progress); $this->em()->flush(); $stamp=$progress->completedAt;
        $saved=$this->save($this->service(new MockCodeRunnerClient()),'draft'); self::assertSame('draft',$saved->status);
        self::assertSame(1,$this->em()->getRepository(TaskProgress::class)->count([])); self::assertSame($stamp,$progress->completedAt);
    }
    public function testStaleExerciseVersionRejected(): void
    {
        $this->prepare(); $this->expectException(Conflict::class);
        $this->service(new MockCodeRunnerClient())->save($this->task,'001',$this->version->id+100,'php','code','draft');
    }
    public function testWrongTestVersionRejected(): void
    {
        $this->prepare(); $service=$this->service(new MockCodeRunnerClient()); $saved=$this->save($service);
        $this->expectException(\DomainException::class);
        $service->acceptTrustedResult($saved,new RunnerResult($saved->runnerJobId,$saved->publicId,'wrong-version','passed',[['id'=>'public_1','passed'=>true]],true));
    }
    public function testHiddenCaseCannotEnterPublicResults(): void
    {
        $this->prepare(); $service=$this->service(new MockCodeRunnerClient()); $saved=$this->save($service);
        $this->expectException(\DomainException::class);
        $service->acceptTrustedResult($saved,new RunnerResult($saved->runnerJobId,$saved->publicId,$this->version->testsVersion,'failed',[['id'=>'SECRET_CASE','passed'=>false]],false));
    }
    public function testOwnerAndRefreshCsrfOverHttp(): void
    {
        $client=$this->prepare(); $saved=$this->save($this->service(new MockCodeRunnerClient()),'draft'); $id=$saved->publicId;
        $saved->userId='other-student'; $this->em()->flush();
        $client->request('GET','/learn/coding/'.$this->task->id.'?solution='.$id.'&userId=other-student'); self::assertResponseStatusCodeSame(404);
        // PBAC conceals another owner's submission before form/CSRF processing.
        $client->request('POST','/learn/coding/solutions/'.$id.'/refresh',['status'=>'passed','score'=>100]); self::assertResponseStatusCodeSame(404);
    }
    public function testStudentApiDoesNotSerializeVersionsOrHiddenTests(): void
    {
        $client=$this->prepare(); $client->request('GET','/api/me/quests'); self::assertResponseIsSuccessful();
        $json=$client->getResponse()->getContent(); self::assertStringContainsString('coding',$json);
        self::assertStringNotContainsString('SECRET',$json); self::assertStringNotContainsString('hiddenTests',$json); self::assertStringNotContainsString('starters',$json);
    }
    public function testSeveralOwnersKeepIndependentProgress(): void
    {
        $this->prepare(); $enrollment=new Enrollment(); $enrollment->userId='second-student'; $enrollment->course=$this->task->card->sprint->course;
        $this->em()->persist($enrollment); $this->em()->flush();
        foreach(['task.view','task.submit'] as $permission)self::getContainer()->get(\App\Access\GrantManager::class)->install('second-student',$permission,'course',$enrollment->course,'phpunit');
        $service=$this->service(new MockCodeRunnerClient()); $first=$this->save($service,'draft');
        $second=$service->save($this->task,'second-student',$this->version->id,'go','package main','submit');
        $service->acceptTrustedResult($second,$this->runnerResult($second));
        self::assertSame('draft',$first->status); self::assertSame(1,$this->em()->getRepository(TaskProgress::class)->count([]));
        self::assertSame('second-student',$this->em()->getRepository(TaskProgress::class)->findOneBy([])->userId);
    }
    public function testPendingPollAndPartialPublicPassCannotComplete(): void
    {
        $this->prepare(); $runner=new MockCodeRunnerClient(); $service=$this->service($runner); $saved=$this->save($service);
        $service->refresh($saved,'001'); self::assertSame('queued',$saved->status); self::assertNull($saved->score);
        $this->expectException(\DomainException::class);
        $service->acceptTrustedResult($saved,new RunnerResult($saved->runnerJobId,$saved->publicId,$this->version->testsVersion,'passed',[],true));
    }
    public function testIllegalTransitionCannotChangeDraftToPassed(): void
    {
        $submission=new CodeSubmission(); $this->expectException(\DomainException::class);
        (new SubmissionTransitions())->move($submission,'passed');
    }
    public function testRevokedSubmitPermissionPreventsNewCompletionFromLateResult(): void
    {
        $this->prepare();$service=$this->service(new MockCodeRunnerClient());$saved=$this->save($service);
        $grant=$this->em()->getRepository(\App\Access\Domain\Entity\PermissionGrant::class)->findOneBy(['userId'=>'001','permission'=>'task.submit']);
        $this->em()->remove($grant);$this->em()->flush();$service->acceptTrustedResult($saved,$this->runnerResult($saved));
        self::assertSame('passed',$saved->status);self::assertSame(0,$this->em()->getRepository(TaskProgress::class)->count([]));
    }
}

