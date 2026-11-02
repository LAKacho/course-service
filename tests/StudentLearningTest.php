<?php
namespace App\Tests;

use App\Learning\Domain\Entity\Course;

use App\Learning\Domain\Entity\Sprint;

use App\Learning\Domain\Entity\Card;

use App\Learning\Domain\Entity\Task;

use App\Learning\Domain\Entity\TaskProgress;

use App\Learning\Domain\Entity\LearningPath;

use App\Learning\Domain\Entity\PathCourse;

use App\Mentor\Domain\Entity\Enrollment;

use App\Mentor\Domain\Entity\QuestPrerequisite;

use App\Student\Domain\Entity\QuestActivity;

use App\Student\Domain\QuestState;
use App\Service\{EnrollmentService, QuestPrerequisiteService, QuestAvailabilityService, StudentQuestService};
use Doctrine\ORM\Tools\SchemaTool;
use App\Shared\Application\Exception\ResourceNotVisible;

class StudentLearningTest extends TestCase
{
    private function prepare(): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client = self::createClient();
        $em = self::getContainer()->get('doctrine')->getManager();
        $params = $em->getConnection()->getParams();
        self::assertSame('pdo_sqlite', $params['driver']);
        self::assertStringEndsWith('var/test.db', str_replace('\\', '/', $params['path']));
        $schema = new SchemaTool($em); $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata); $schema->createSchema($metadata);
        \App\Tests\Support\LegacyAccessFixture::install($em);
        $course = new Course(); $course->title = 'Назначаемый курс'; $em->persist($course);
        $path = new LearningPath(); $path->title = 'Назначаемое направление'; $path->slug = 'assigned-path'; $path->description = 'SECRET_FULL_OUTLINE'; $em->persist($path);
        $link = new PathCourse($path); $link->course = $course; $em->persist($link);
        $sprint = new Sprint(); $sprint->course = $course; $sprint->title = 'Спринт'; $em->persist($sprint);
        $card = new Card(); $card->sprint = $sprint; $card->title = 'Карточка'; $em->persist($card);
        foreach (['Первый квест', 'HIDDEN_QUEST_A', 'HIDDEN_QUEST_B'] as $index => $title) {
            $task = new Task(); $task->card = $card; $task->title = $title; $task->description = 'Описание '.$title; $task->position = $index;
            $em->persist($task);
        }
        $em->flush();
        $this->condition(2, 1); $this->condition(3, 2);
        \App\Tests\Support\DemoSession::attach($client); return $client;
    }

    private function em() { return self::getContainer()->get('doctrine')->getManager(); }

    private function condition(int $taskId, int $requiredId): void
    {
        $condition = new QuestPrerequisite();
        $condition->task = $this->em()->find(Task::class, $taskId);
        $condition->requiredTask = $this->em()->find(Task::class, $requiredId);
        self::getContainer()->get(QuestPrerequisiteService::class)->save($condition);
    }

    private function assign(string $userId = '001', bool $path = false): void
    {
        $enrollment = new Enrollment(); $enrollment->userId = $userId;
        if ($path) { $enrollment->learningPath = $this->em()->find(LearningPath::class, 1); }
        else { $enrollment->course = $this->em()->find(Course::class, 1); }
        self::getContainer()->get(EnrollmentService::class)->save($enrollment);
    }

    private function json($client, string $route): array
    {
        $client->request('GET', $route);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');
        self::assertStringContainsString('no-store', $client->getResponse()->headers->get('Cache-Control'));
        return json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testNoEnrollmentHidesProgramAndHistoricalProgress(): void
    {
        $client = $this->prepare();
        $this->em()->persist(new TaskProgress('001', $this->em()->find(Task::class, 1))); $this->em()->flush();
        self::assertSame(QuestState::Locked, self::getContainer()->get(QuestAvailabilityService::class)->state($this->em()->find(Task::class, 1), '001'));
        self::assertSame([], $this->json($client, '/api/me/quests'));
        self::assertSame(['learningPaths' => [], 'courses' => []], $this->json($client, '/api/me/learning-paths'));
        self::assertSame(['visible' => 0, 'available' => 0, 'inProgress' => 0, 'completed' => 0, 'percentVisible' => 0], $this->json($client, '/api/me/progress'));
        $client->request('GET', '/learn');
        self::assertSelectorTextContains('main', 'Программа пока не назначена');
        self::assertStringNotContainsString('HIDDEN_QUEST', $client->getResponse()->getContent());
    }

    public function testInitialAvailabilityLockedQuestAndServerSideWriteGuard(): void
    {
        $client = $this->prepare(); $this->assign();
        $availability = self::getContainer()->get(QuestAvailabilityService::class);
        self::assertSame(QuestState::Available, $availability->state($this->em()->find(Task::class, 1), '001'));
        self::assertSame(QuestState::Locked, $availability->state($this->em()->find(Task::class, 2), '001'));
        $quests = $this->json($client, '/api/me/quests');
        self::assertSame([1], array_column($quests, 'id')); self::assertSame('available', $quests[0]['state']);
        try {
            self::getContainer()->get(StudentQuestService::class)->complete($this->em()->find(Task::class, 2));
            self::fail('Locked tasks must be rejected even without an HTTP controller');
        } catch (ResourceNotVisible) { self::assertTrue(true); }
        self::assertSame(0, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM task_progress'));
    }

    public function testStartingAndCompletionOpenNextWithoutDuplicateTruth(): void
    {
        $client = $this->prepare(); $this->assign();
        $crawler = $client->request('GET', '/learn');
        $start = $crawler->filter('form[action="/learn/quests/1/start"]')->form();
        $client->submit($start); self::assertResponseRedirects('/learn', 303);
        $client->submit($start); self::assertResponseStatusCodeSame(303);
        $quests = $this->json($client, '/api/me/quests');
        self::assertSame('in_progress', $quests[0]['state']);
        self::assertSame(1, $this->em()->getRepository(QuestActivity::class)->count([]));
        self::assertSame(0, $this->em()->getRepository(TaskProgress::class)->count([]));
        $crawler = $client->request('GET', '/learn');
        $complete = $crawler->filter('form[action="/learn/quests/1/complete"]')->form();
        $client->submit($complete); self::assertResponseStatusCodeSame(303);
        $client->submit($complete); self::assertResponseStatusCodeSame(303);
        $quests = $this->json($client, '/api/me/quests');
        self::assertSame([1, 2], array_column($quests, 'id'));
        self::assertSame(['completed', 'available'], array_column($quests, 'state'));
        self::assertSame(1, $this->em()->getRepository(TaskProgress::class)->count([]));
        self::assertSame(['visible' => 2, 'available' => 1, 'inProgress' => 0, 'completed' => 1, 'percentVisible' => 50], $this->json($client, '/api/me/progress'));
        $crawler = $client->request('GET', '/learn');
        $client->submit($crawler->filter('form[action="/learn/quests/2/complete"]')->form());
        self::assertSame([1, 2, 3], array_column($this->json($client, '/api/me/quests'), 'id'));
    }

    public function testQueryUserIdCannotSelectAnotherStudent(): void
    {
        $client = $this->prepare(); $this->assign('other-student');
        $this->em()->persist(new TaskProgress('other-student', $this->em()->find(Task::class, 1))); $this->em()->flush();
        self::assertSame([], $this->json($client, '/api/me/quests?userId=other-student'));
        self::assertSame(['learningPaths' => [], 'courses' => []], $this->json($client, '/api/me/learning-paths?userId=other-student'));
        self::assertSame(0, $this->json($client, '/api/me/progress?userId=other-student')['completed']);
        $this->assign();
        $quests = $this->json($client, '/api/me/quests?userId=other-student');
        self::assertSame([1], array_column($quests, 'id'));
        self::assertSame('available', $quests[0]['state']);
    }

    public function testHiddenContentAndHiddenCountsNeverEnterStudentResponses(): void
    {
        $client = $this->prepare(); $this->assign(path: true);
        $before = [];
        foreach (['/api/me/quests', '/api/me/learning-paths', '/api/me/progress'] as $route) { $before[$route] = $this->json($client, $route); }
        $em = $this->em();
        $card = $em->find(Card::class, 1); $required = $em->find(Task::class, 2);
        for ($index = 0; $index < 40; ++$index) {
            $task = new Task(); $task->card = $card; $task->title = 'SECRET_ADDED_'.$index; $task->description = 'SECRET_ANSWER_'.$index; $task->contentUrl = 'https://example.com/SECRET_'.$index;
            $em->persist($task);
            $edge = new QuestPrerequisite(); $edge->task = $task; $edge->requiredTask = $required; $em->persist($edge);
        }
        $em->flush();
        foreach ($before as $route => $data) {
            self::assertSame($data, $this->json($client, $route));
            self::assertStringNotContainsString('SECRET', $client->getResponse()->getContent());
            self::assertStringNotContainsString('HIDDEN_QUEST', $client->getResponse()->getContent());
        }
        $client->request('GET', '/learn');
        self::assertStringNotContainsString('SECRET', $client->getResponse()->getContent());
        self::assertStringNotContainsString('HIDDEN_QUEST', $client->getResponse()->getContent());
        $client->request('GET', '/api/cards/1/tasks');
        self::assertResponseIsSuccessful();
        self::assertCount(43, json_decode($client->getResponse()->getContent(), true));
        self::assertStringContainsString('SECRET_ADDED', $client->getResponse()->getContent());
    }

    public function testPathAndDirectCourseAssignmentsDeduplicateQuests(): void
    {
        $client = $this->prepare(); $this->assign(path: true); $this->assign();
        self::assertCount(1, $this->json($client, '/api/me/quests'));
        $programs = $this->json($client, '/api/me/learning-paths');
        self::assertCount(1, $programs['learningPaths']); self::assertCount(1, $programs['courses']);
        self::assertSame(['id', 'title'], array_keys($programs['learningPaths'][0]));
    }

    public function testLegacyCompletionRemainsTheOnlySourceOfCompletion(): void
    {
        $client = $this->prepare(); $this->assign();
        $progress = new TaskProgress('001', $this->em()->find(Task::class, 3));
        $this->em()->persist($progress); $this->em()->flush(); $id = $progress->id;
        $quests = $this->json($client, '/api/me/quests');
        self::assertSame([1, 3], array_column($quests, 'id'));
        self::assertSame(['available', 'completed'], array_column($quests, 'state'));
        self::assertSame(0, $this->em()->getRepository(QuestActivity::class)->count([]));
        self::assertSame($id, $this->em()->getRepository(TaskProgress::class)->findOneBy(['userId' => '001'])->id);
    }

    public function testCyclesAndInvalidEditingAreRejectedWithoutSaving(): void
    {
        $client = $this->prepare();
        foreach ([2, 3, 1] as $required) {
            $crawler = $client->request('GET', '/mentor/prerequisites/new');
            $client->submit($crawler->selectButton('Сохранить')->form(['quest_prerequisite[task]' => 1, 'quest_prerequisite[requiredTask]' => $required]));
            self::assertResponseStatusCodeSame(422);
            self::assertSelectorTextContains('main', 'Циклическая зависимость');
            self::assertSame(2, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM quest_prerequisite'));
        }
        $crawler = $client->request('GET', '/mentor/prerequisites/1/edit');
        $client->submit($crawler->selectButton('Сохранить')->form(['quest_prerequisite[requiredTask]' => 3]));
        self::assertResponseStatusCodeSame(422);
        self::assertSame(1, (int) $this->em()->getConnection()->fetchOne('SELECT required_task_id FROM quest_prerequisite WHERE id = 1'));
    }

    public function testMentorEnrollmentValidationDuplicatesAndStudentCsrf(): void
    {
        $client = $this->prepare();
        foreach ([['', ''], ['1', '1']] as [$path, $course]) {
            $crawler = $client->request('GET', '/mentor/enrollments/new');
            $client->submit($crawler->selectButton('Сохранить')->form(['enrollment[learningPath]' => $path, 'enrollment[course]' => $course]));
            self::assertResponseStatusCodeSame(422);
        }
        $crawler = $client->request('GET', '/mentor/enrollments/new');
        $client->submit($crawler->selectButton('Сохранить')->form(['enrollment[course]' => 1]));
        self::assertResponseRedirects('/mentor/learning', 303);
        $crawler = $client->request('GET', '/mentor/enrollments/new');
        $client->submit($crawler->selectButton('Сохранить')->form(['enrollment[course]' => 1]));
        self::assertResponseStatusCodeSame(422);
        self::assertSame(1, $this->em()->getRepository(Enrollment::class)->count([]));
        $client->request('POST', '/learn/quests/1/start'); self::assertResponseStatusCodeSame(403);
        $client->request('POST', '/learn/quests/1/complete'); self::assertResponseStatusCodeSame(403);
        $client->request('POST', '/mentor/enrollments/new', ['enrollment' => ['course' => 1, 'userId' => 'forged']]);
        self::assertResponseStatusCodeSame(422);
        $client->request('POST', '/api/me/quests'); self::assertResponseStatusCodeSame(405);
    }
}
