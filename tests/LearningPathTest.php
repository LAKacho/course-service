<?php
namespace App\Tests;

use App\DataFixtures\{AppFixtures, LearningPathFixtures};
use App\Learning\Domain\Entity\Course;
use App\Learning\Domain\Entity\LearningPath;
use App\Learning\Domain\Entity\PathCourse;
use App\Learning\Domain\Entity\Task;
use App\Learning\Domain\Entity\TaskProgress;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\Tools\SchemaTool;

class LearningPathTest extends TestCase
{
    private function prepare(): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client = self::createClient();
        $em = self::getContainer()->get('doctrine')->getManager();
        $params = $em->getConnection()->getParams();
        self::assertSame('pdo_sqlite', $params['driver']);
        self::assertStringEndsWith('var/test.db', str_replace('\\', '/', $params['path']));
        $schema = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        \App\Tests\Support\LegacyAccessFixture::install($em);
        (new AppFixtures())->load($em);
        (new LearningPathFixtures())->load($em);
        $course = new Course();
        $course->title = 'Другой курс';
        $em->persist($course);
        $em->flush();
        \App\Tests\Support\DemoSession::attach($client); return $client;
    }

    private function attach($client, int $path, int $course, int $position): void
    {
        $crawler = $client->request('GET', '/learning-paths/'.$path.'/courses/add');
        $client->submit($crawler->selectButton('Добавить курс')->form([
            'path_course[course]' => $course, 'path_course[position]' => $position,
        ]));
        self::assertResponseRedirects('/learning-paths/'.$path, 303);
    }

    public function testCreateEditAndApi(): void
    {
        $client = $this->prepare();
        $client->request('GET', '/learning-paths');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Go Backend Developer');
        $crawler = $client->request('GET', '/learning-paths/new');
        $client->submit($crawler->selectButton('Сохранить направление')->form([
            'learning_path[title]' => 'Backend', 'learning_path[slug]' => 'backend',
            'learning_path[description]' => 'Путь обучения', 'learning_path[status]' => 'published',
        ]));
        self::assertResponseRedirects('/learning-paths/2', 303);
        $client->followRedirect();
        self::assertSelectorTextContains('h1', 'Backend');
        self::assertSelectorTextContains('main', 'Курсы пока не добавлены');
        $em = self::getContainer()->get('doctrine')->getManager();
        $path = $em->find(LearningPath::class, 2);
        $createdAt = $path->createdAt;
        $path->updatedAt = new \DateTimeImmutable('2000-01-01');
        $em->flush();
        $crawler = $client->request('GET', '/learning-paths/2/edit');
        $client->submit($crawler->selectButton('Сохранить направление')->form(['learning_path[title]' => 'Backend обновлён', 'learning_path[status]' => 'archived']));
        self::assertResponseStatusCodeSame(303);
        $client->request('GET', '/api/learning-paths/2');
        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Backend обновлён', $data['title']);
        self::assertSame('archived', $data['status']);
        self::assertSame($createdAt->format(DATE_ATOM), $data['createdAt']);
        self::assertGreaterThan('2000-01-01', $data['updatedAt']);
        $client->request('GET', '/api/learning-paths');
        self::assertCount(2, json_decode($client->getResponse()->getContent(), true));
        $client->request('GET', '/api/learning-paths/2/courses');
        self::assertSame([], json_decode($client->getResponse()->getContent(), true));
    }

    public function testSharedCoursesStableOrderingAndPositionForm(): void
    {
        $client = $this->prepare();
        $em = self::getContainer()->get('doctrine')->getManager();
        $other = new LearningPath(); $other->title = 'Другой путь'; $other->slug = 'other';
        $em->persist($other); $em->flush();
        $this->attach($client, 1, 1, 10);
        $this->attach($client, 1, 2, 10);
        $this->attach($client, 2, 1, 0);
        $client->request('GET', '/api/learning-paths/1/courses');
        $courses = json_decode($client->getResponse()->getContent(), true);
        self::assertSame([1, 2], array_column($courses, 'id'));
        self::assertSame([10, 10], array_column($courses, 'position'));
        $linkId = $courses[1]['pathCourseId'];
        $crawler = $client->request('GET', '/learning-paths/1');
        $client->submit($crawler->filter('form[action="/learning-paths/1/courses/'.$linkId.'/position"]')->form([
            'path_course_position_'.$linkId.'[position]' => 0,
        ]));
        self::assertResponseRedirects('/learning-paths/1', 303);
        $client->request('GET', '/api/learning-paths/1/courses');
        self::assertSame([2, 1], array_column(json_decode($client->getResponse()->getContent(), true), 'id'));
        $client->request('GET', '/api/learning-paths/2/courses');
        self::assertSame([1], array_column(json_decode($client->getResponse()->getContent(), true), 'id'));
    }

    public function testValidationAndDuplicateSlugAndCourse(): void
    {
        $client = $this->prepare();
        foreach (['go-backend-developer', 'Bad Slug', ''] as $slug) {
            $crawler = $client->request('GET', '/learning-paths/new');
            $client->submit($crawler->selectButton('Сохранить направление')->form(['learning_path[title]' => 'Название', 'learning_path[slug]' => $slug]));
            self::assertResponseStatusCodeSame(422);
        }
        $crawler = $client->request('GET', '/learning-paths/new');
        $client->submit($crawler->selectButton('Сохранить направление')->form(['learning_path[title]' => ' ', 'learning_path[slug]' => 'valid']));
        self::assertResponseStatusCodeSame(422);
        $this->attach($client, 1, 1, 0);
        $crawler = $client->request('GET', '/learning-paths/1/courses/add');
        $client->submit($crawler->selectButton('Добавить курс')->form(['path_course[course]' => 1, 'path_course[position]' => 1]));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Этот курс уже добавлен');
        $crawler = $client->request('GET', '/learning-paths/1/courses/add');
        $client->submit($crawler->selectButton('Добавить курс')->form(['path_course[course]' => 2, 'path_course[position]' => -1]));
        self::assertResponseStatusCodeSame(422);
        $crawler = $client->request('GET', '/learning-paths/1/courses/add');
        $client->submit($crawler->selectButton('Добавить курс')->form(['path_course[course]' => '', 'path_course[position]' => 0]));
        self::assertResponseStatusCodeSame(422);
        $client->request('POST', '/learning-paths/1/courses/add', ['path_course' => ['course' => 99999, 'position' => 0]]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(1, self::getContainer()->get('doctrine')->getManager()->getRepository(PathCourse::class)->count([]));
    }

    public function testCsrfPositionScopingAndNotFoundResponses(): void
    {
        $client = $this->prepare();
        $client->request('POST', '/learning-paths/new', ['learning_path' => ['title' => 'Unsafe', 'slug' => 'unsafe', 'status' => 'draft']]);
        self::assertResponseStatusCodeSame(422);
        $client->request('POST', '/learning-paths/1/edit', ['learning_path' => ['title' => 'Unsafe', 'slug' => 'unsafe', 'status' => 'draft']]);
        self::assertResponseStatusCodeSame(422);
        $client->request('POST', '/learning-paths/1/courses/add', ['path_course' => ['course' => 1, 'position' => 0]]);
        self::assertResponseStatusCodeSame(422);
        $this->attach($client, 1, 1, 5);
        $client->request('POST', '/learning-paths/1/courses/1/position', ['path_course_position_1' => ['position' => 0]]);
        self::assertResponseStatusCodeSame(422);
        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear(); // Verify persisted state, not the object mapped by the rejected form.
        self::assertSame(5, $em->find(PathCourse::class, 1)->position);
        self::assertSame('Go Backend Developer', $em->find(LearningPath::class, 1)->title);
        $other = new LearningPath(); $other->title = 'Other'; $other->slug = 'other'; $em->persist($other); $em->flush();
        $client->request('POST', '/learning-paths/2/courses/1/position');
        self::assertResponseStatusCodeSame(404);
        foreach (['/api/learning-paths/999', '/api/learning-paths/999/courses'] as $url) {
            $client->request('GET', $url);
            self::assertResponseStatusCodeSame(404);
            self::assertResponseHeaderSame('content-type', 'application/json');
        }
        $client->request('POST', '/api/learning-paths');
        self::assertResponseStatusCodeSame(405);
        $client->request('GET', '/learning-paths/999');
        self::assertResponseStatusCodeSame(404);
    }

    public function testDatabaseUniqueConstraints(): void
    {
        $client = $this->prepare();
        $this->attach($client, 1, 1, 0);
        $connection = self::getContainer()->get('doctrine')->getConnection();
        foreach ([
            "INSERT INTO learning_path (title, slug, description, status, created_at, updated_at) VALUES ('Duplicate', 'go-backend-developer', '', 'draft', '2026-01-01', '2026-01-01')",
            'INSERT INTO path_course (path_id, course_id, position) VALUES (1, 1, 9)',
        ] as $sql) {
            try {
                $connection->executeStatement($sql);
                self::fail('Database must reject duplicate values');
            } catch (UniqueConstraintViolationException) {
                self::assertTrue(true);
            }
        }
    }

    public function testRemovingLinkDoesNotRemoveLearningDataAndFixturesAreIdempotent(): void
    {
        $client = $this->prepare();
        $this->attach($client, 1, 1, 0);
        $em = self::getContainer()->get('doctrine')->getManager();
        $task = $em->find(Task::class, 1);
        $em->persist(new TaskProgress('001', $task)); $em->flush();
        $em->remove($em->find(PathCourse::class, 1)); $em->flush();
        self::assertNotNull($em->find(Course::class, 1));
        self::assertSame(15, $em->getRepository(Task::class)->count([]));
        self::assertSame(1, $em->getRepository(TaskProgress::class)->count([]));
        (new LearningPathFixtures())->load($em);
        (new LearningPathFixtures())->load($em);
        self::assertSame(1, $em->getRepository(LearningPath::class)->count([]));
        self::assertSame(2, $em->getRepository(Course::class)->count([]));
    }
}
