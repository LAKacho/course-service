<?php
namespace App\Tests;

use App\Entity\{Course, Sprint, Card, Task, LearningPath, PathCourse, Enrollment, QuestPrerequisite, QuestActivity};
use App\Service\{StudentPathPresenter, QuestPrerequisiteService};
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RpgPresentationTest extends WebTestCase
{
    private function prepare(): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client = self::createClient(); $em = self::getContainer()->get('doctrine')->getManager();
        $params = $em->getConnection()->getParams();
        self::assertSame('pdo_sqlite', $params['driver']);
        self::assertStringEndsWith('var/test.db', str_replace('\\', '/', $params['path']));
        $schema = new SchemaTool($em); $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata); $schema->createSchema($metadata);
        \App\Tests\Support\LegacyAccessFixture::install($em);
        $course = new Course(); $course->title = 'Курс атласа'; $em->persist($course);
        $path = new LearningPath(); $path->title = 'Маршрут'; $path->slug = 'route'; $path->description = 'PRIVATE_OUTLINE'; $em->persist($path);
        $link = new PathCourse($path); $link->course = $course; $em->persist($link);
        foreach (['Первая глава', 'SECRET_CHAPTER'] as $index => $title) {
            $sprint = new Sprint(); $sprint->title = $title; $sprint->course = $course; $sprint->position = $index; $em->persist($sprint);
            $card = new Card(); $card->title = $title; $card->sprint = $sprint; $em->persist($card);
            $task = new Task(); $task->card = $card; $task->title = $index === 0 ? 'Первый квест' : 'SECRET_QUEST'; $task->description = $index === 0 ? 'Знакомый шаг' : 'SECRET_ANSWER'; $task->position = 0; $em->persist($task);
        }
        $enrollment = new Enrollment(); $enrollment->course = $course; $em->persist($enrollment); $em->flush();
        $condition = new QuestPrerequisite(); $condition->task = $em->find(Task::class, 2); $condition->requiredTask = $em->find(Task::class, 1);
        self::getContainer()->get(QuestPrerequisiteService::class)->save($condition);
        \App\Tests\Support\DemoSession::attach($client); return $client;
    }

    public function testMapHasNoHiddenChapterAndMentorSeesFullOutline(): void
    {
        $client = $this->prepare();
        $model = self::getContainer()->get(StudentPathPresenter::class)->present('demo-student');
        self::assertCount(1, $model['chapters']); self::assertSame('Первая глава', $model['chapters'][0]['title']);
        self::assertSame(1, $model['currentQuest']['id']);
        $client->request('GET', '/learn'); self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Мой путь'); self::assertSelectorExists('a[data-quest-jump]');
        self::assertStringNotContainsString('SECRET', $client->getResponse()->getContent());
        self::assertStringNotContainsString('PRIVATE_OUTLINE', $client->getResponse()->getContent());
        $client->request('GET', '/mentor/programs/1'); self::assertResponseIsSuccessful();
        self::assertStringContainsString('SECRET_CHAPTER', $client->getResponse()->getContent());
        self::assertStringContainsString('SECRET_QUEST', $client->getResponse()->getContent());
    }

    public function testCompletingQuestRevealsChapterThroughBackend(): void
    {
        $client = $this->prepare(); $crawler = $client->request('GET', '/learn');
        $client->submit($crawler->filter('form[action="/learn/quests/1/complete"]')->form());
        self::assertResponseRedirects('/learn', 303); $client->followRedirect();
        self::assertSelectorExists('.task-done');
        $model = self::getContainer()->get(StudentPathPresenter::class)->present('demo-student');
        self::assertCount(2, $model['chapters']); self::assertSame(2, $model['currentQuest']['id']);
        self::assertTrue($model['chapters'][0]['knownCompleted']);
        self::assertSame(50, $model['progress']['percentVisible']);
    }

    public function testCurrentQuestPrefersStartedAndAllTypesAreDistinct(): void
    {
        $client = $this->prepare(); $em = self::getContainer()->get('doctrine')->getManager();
        foreach (['practice', 'video', 'question'] as $type) {
            $task = new Task(); $task->title = 'Открытый '.$type; $task->type = $type; $task->card = $em->find(Card::class, 1); $em->persist($task);
        }
        $em->flush(); $em->persist(new QuestActivity('demo-student', $em->find(Task::class, 4))); $em->flush();
        $model = self::getContainer()->get(StudentPathPresenter::class)->present('demo-student');
        self::assertSame(4, $model['currentQuest']['id']);
        $client->request('GET', '/learn'); self::assertResponseIsSuccessful();
        foreach (['reading', 'video', 'practice', 'question'] as $type) { self::assertSelectorExists('.quest-'.$type); }
        self::assertSelectorTextContains('.quest-current', 'Открытый video');
    }
}
