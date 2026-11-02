<?php
namespace App\Tests;
use App\DataFixtures\AppFixtures;
use App\Learning\Domain\Entity\Course;
use App\Learning\Domain\Entity\Sprint;
use App\Learning\Domain\Entity\Card;
use App\Learning\Domain\Entity\Task;
use App\Learning\Domain\Entity\TaskProgress;
use Doctrine\ORM\Tools\SchemaTool;
class ApplicationTest extends TestCase
{
    private function prepare(): \Symfony\Bundle\FrameworkBundle\KernelBrowser {
        $client = self::createClient();
        $em = self::getContainer()->get('doctrine')->getManager();
        // Tests may rebuild only the explicit disposable SQLite database.
        $params = $em->getConnection()->getParams();
        self::assertSame('pdo_sqlite', $params['driver']);
        self::assertStringEndsWith('var/test.db', str_replace('\\', '/', $params['path']));
        $tool = new SchemaTool($em); $metadata = $em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata); $tool->createSchema($metadata);
        \App\Tests\Support\LegacyAccessFixture::install($em);
        (new AppFixtures())->load($em);
        \App\Tests\Support\DemoSession::attach($client); return $client;
    }
    public function testPagesApiAndErrors(): void {
        $client = $this->prepare();
        foreach (['/', '/courses', '/courses/1', '/sprints/1', '/cards/1', '/manage/course/new', '/manage/sprint/new/1', '/manage/card/new/1', '/manage/task/new/1', '/manage/course/1/edit', '/manage/sprint/1/edit', '/manage/card/1/edit', '/manage/task/1/edit'] as $url) {
            $client->request('GET', $url); self::assertResponseIsSuccessful($url);
        }
        foreach (['/api/courses', '/api/courses/1', '/api/courses/1/sprints', '/api/sprints/1/cards', '/api/cards/1/tasks', '/api/courses/1/progress'] as $url) {
            $client->request('GET', $url); self::assertResponseIsSuccessful($url);
            self::assertResponseHeaderSame('content-type', 'application/json');
            self::assertIsArray(json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
        }
        foreach (['/api/courses/9999', '/api/courses/9999/sprints', '/api/sprints/9999/cards', '/api/cards/9999/tasks', '/api/courses/9999/progress', '/api/unknown'] as $url) {
            $client->request('GET', $url); self::assertResponseStatusCodeSame(404);
            self::assertSame(404, json_decode($client->getResponse()->getContent(), true)['error']['status']);
        }
        $client->request('POST', '/api/courses'); self::assertResponseStatusCodeSame(405);
        $client->request('GET', '/courses/9999'); self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('h1', 'не найдена');
    }
    public function testCompletionIsIdempotentAndIndependentOfCardStatus(): void {
        $client = $this->prepare();
        $crawler = $client->request('GET', '/sprints/1');
        $form = $crawler->filter('form[action="/cards/1/status"]')->form(['status' => 'done']);
        $client->submit($form); self::assertResponseRedirects('/sprints/1', 303);
        $client->request('GET', '/api/courses/1/progress');
        $initial = json_decode($client->getResponse()->getContent(), true);
        self::assertGreaterThan(0, $initial['total']); self::assertSame(0, $initial['completed']);
        $crawler = $client->request('GET', '/cards/1');
        $form = $crawler->filter('form[action="/tasks/1/completion"]')->form();
        $client->submit($form); self::assertResponseRedirects('/cards/1', 303);
        $client->submit($form); self::assertResponseStatusCodeSame(303);
        $client->request('GET', '/api/courses/1/progress');
        $progress = json_decode($client->getResponse()->getContent(), true);
        self::assertSame(1, $progress['completed']); self::assertSame((int) round(100 / $initial['total']), $progress['percent']);
        // Another user's completion must not increase the demo student's progress.
        $em = self::getContainer()->get('doctrine')->getManager();
        $em->persist(new TaskProgress('other-student', $em->find(Task::class, 2))); $em->flush();
        $client->request('GET', '/api/courses/1/progress');
        self::assertSame(1, json_decode($client->getResponse()->getContent(), true)['completed']);
        $crawler = $client->request('GET', '/cards/1');
        $client->submit($crawler->filter('form[action="/tasks/1/completion"]')->form());
        $client->request('GET', '/api/courses/1/progress'); self::assertSame(0, json_decode($client->getResponse()->getContent(), true)['completed']);
    }
    public function testFormsValidationCsrfAndCreation(): void {
        $client = $this->prepare();
        $crawler = $client->request('GET', '/manage/course/new');
        $client->submit($crawler->selectButton('Сохранить изменения')->form(['learning[title]' => ' ', 'learning[description]' => 'Тест']));
        self::assertResponseStatusCodeSame(422);
        $crawler = $client->request('GET', '/manage/course/new');
        $client->submit($crawler->selectButton('Сохранить изменения')->form(['learning[title]' => 'Новый курс', 'learning[description]' => 'Описание']));
        self::assertResponseStatusCodeSame(303); $client->followRedirect(); self::assertSelectorTextContains('h1', 'Новый курс');
        $client->request('GET', '/api/courses/2/progress');
        self::assertSame(['courseId' => 2, 'total' => 0, 'completed' => 0, 'percent' => 0], json_decode($client->getResponse()->getContent(), true));
        foreach ([['sprint', 'Спринт'], ['card', 'Карточка'], ['task', 'Задание']] as [$kind, $title]) {
            $crawler = $client->request('GET', '/manage/'.$kind.'/new/1');
            $client->submit($crawler->selectButton('Сохранить изменения')->form(['learning[title]' => $title, 'learning[description]' => 'Проверка формы']));
            self::assertResponseStatusCodeSame(303);
        }
        $crawler = $client->request('GET', '/manage/task/1/edit');
        $client->submit($crawler->selectButton('Сохранить изменения')->form(['learning[contentUrl]' => 'javascript:alert(1)']));
        self::assertResponseStatusCodeSame(422);
        $crawler = $client->request('GET', '/manage/course/1/edit');
        $client->submit($crawler->selectButton('Сохранить изменения')->form(['learning[title]' => 'Обновлённый курс']));
        self::assertResponseStatusCodeSame(303);
        $client->request('POST', '/cards/1/status', ['status' => 'done']); self::assertResponseStatusCodeSame(403);
        $client->request('POST', '/tasks/1/completion', ['completed' => '1']); self::assertResponseStatusCodeSame(403);
        $crawler = $client->request('GET', '/sprints/1');
        $token = $crawler->filter('form[action="/cards/1/status"] input[name="_token"]')->attr('value');
        $client->request('POST', '/cards/1/status', ['status' => 'unknown', '_token' => $token]); self::assertResponseStatusCodeSame(400);
    }
    public function testFixturesAreSafeToAppendAndOrderingIsStable(): void {
        $this->prepare();
        $em = self::getContainer()->get('doctrine')->getManager();
        (new AppFixtures())->load($em);
        self::assertSame(1, $em->getRepository(Course::class)->count([]));
        self::assertSame(4, $em->getRepository(Sprint::class)->count([]));
        $repository = self::getContainer()->get(\App\Repository\LearningRepository::class);
        $course = $em->find(Course::class, 1);
        $sprint = new Sprint(); $sprint->course = $course; $sprint->title = 'Первый'; $sprint->position = 0; $em->persist($sprint); $em->flush();
        self::assertSame('Первый', $repository->sprints($course)[0]->title);
        $violations = self::getContainer()->get('validator')->validate($em->find(Task::class, 1));
        self::assertCount(0, $violations);
    }
}
