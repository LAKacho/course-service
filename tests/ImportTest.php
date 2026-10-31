<?php
namespace App\Tests;

use App\Entity\{Course, LearningPath, Task, TaskProgress};
use App\Service\ImportService;
use App\Service\Import\ImportValidationException;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ImportTest extends KernelTestCase
{
    private function prepare(): array
    {
        self::bootKernel();
        $em = self::getContainer()->get('doctrine')->getManager();
        $params = $em->getConnection()->getParams();
        self::assertSame('pdo_sqlite', $params['driver']);
        self::assertStringEndsWith('var/test.db', str_replace('\\', '/', $params['path']));
        $tool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
        \App\Tests\Support\LegacyAccessFixture::install($em);
        $legacyPath = new LearningPath();
        $legacyPath->title = 'Go Backend Developer'; $legacyPath->slug = 'go-backend-developer';
        $em->persist($legacyPath);
        $legacyCourse = new Course(); $legacyCourse->title = 'Go: от консоли к HTTP';
        $em->persist($legacyCourse); $em->flush();
        $program = json_decode(file_get_contents(dirname(__DIR__).'/examples/go-backend-developer.json'));
        $program->source = 'test';
        $program->learningPath->slug = 'test-go-program';
        return [$em, self::getContainer()->get(ImportService::class), $program];
    }

    private function file(mixed $program): string
    {
        $file = dirname(__DIR__).'/var/test-program.json';
        file_put_contents($file, json_encode($program, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        return $file;
    }

    private function fingerprint($connection): string
    {
        $rows = [];
        foreach (['learning_path', 'path_course', 'course', 'sprint', 'card', 'learning_task', 'task_progress'] as $table) {
            $rows[$table] = $connection->fetchAllAssociative('SELECT * FROM '.$table.' ORDER BY id');
        }
        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }

    public function testDryRunDoesNotWriteOrDirtyManagedObjects(): void
    {
        [$em, $importer, $program] = $this->prepare();
        $before = $this->fingerprint($em->getConnection());
        $report = $importer->import($this->file($program), true);
        self::assertTrue($report['dryRun']);
        self::assertSame(4, $report['counts']['task']['created']);
        self::assertSame(1, $report['counts']['path_course']['created']);
        self::assertSame($before, $this->fingerprint($em->getConnection()));
        // Even an accidental later flush must not persist a dry-run's proposed updates.
        $program->learningPath->slug = 'go-backend-developer';
        $program->learningPath->title = 'Dry-run title';
        $report = $importer->import($this->file($program), true, 1);
        self::assertSame(1, $report['counts']['path']['updated']);
        $em->flush();
        self::assertSame($before, $this->fingerprint($em->getConnection()));
    }

    public function testImportRepeatAndRenameUseExternalIdentity(): void
    {
        [$em, $importer, $program] = $this->prepare();
        $file = $this->file($program);
        $first = $importer->import($file);
        self::assertSame(1, $first['counts']['path']['created']);
        self::assertSame(4, $first['counts']['task']['created']);
        self::assertSame(2, $em->getRepository(Course::class)->count([])); // identical title is not identity
        $task = $em->getRepository(Task::class)->findOneBy(['importKey' => 'test:go-module-reading']);
        $taskId = $task->id;
        $before = $this->fingerprint($em->getConnection());
        $repeat = $importer->import($file);
        foreach ($repeat['counts'] as $count) {
            self::assertSame(0, $count['created']); self::assertSame(0, $count['updated']);
        }
        self::assertSame($before, $this->fingerprint($em->getConnection()));
        $program->learningPath->courses[0]->sprints[0]->cards[0]->tasks[0]->title = 'Переименованное задание';
        $updated = $importer->import($this->file($program));
        self::assertSame(1, $updated['counts']['task']['updated']);
        self::assertSame($taskId, $em->getRepository(Task::class)->findOneBy(['title' => 'Переименованное задание'])->id);
    }

    public function testExplicitBindingAndSlugConflict(): void
    {
        [$em, $importer, $program] = $this->prepare();
        $program->learningPath->slug = 'go-backend-developer';
        try {
            $importer->import($this->file($program), true);
            self::fail('Existing slug must not be adopted implicitly');
        } catch (ImportValidationException $exception) {
            self::assertStringContainsString('--path-id=1', $exception->getMessage());
        }
        $report = $importer->import($this->file($program), false, 1);
        self::assertSame(1, $report['counts']['path']['updated']);
        self::assertSame(1, $em->getRepository(LearningPath::class)->count([]));
        self::assertSame('test:go-backend', $em->find(LearningPath::class, 1)->importKey);
    }

    public function testProgressIsPreservedAndProtectedChangesFailAtomically(): void
    {
        [$em, $importer, $program] = $this->prepare();
        $importer->import($this->file($program));
        $task = $em->getRepository(Task::class)->findOneBy(['importKey' => 'test:go-module-reading']);
        $progress = new TaskProgress('another-student', $task);
        $em->persist($progress); $em->flush();
        $id = $task->id; $completedAt = $progress->completedAt;
        $before = $this->fingerprint($em->getConnection());
        $importer->import($this->file($program));
        self::assertSame($before, $this->fingerprint($em->getConnection()));
        $program->learningPath->courses[0]->title = 'Не должно сохраниться';
        $program->learningPath->courses[0]->sprints[0]->cards[0]->tasks[0]->description = 'Новая работа вместо старой';
        try {
            $importer->import($this->file($program));
            self::fail('A completed task must be protected');
        } catch (ImportValidationException $exception) {
            self::assertStringContainsString('имеет прогресс', $exception->getMessage());
            self::assertStringContainsString('tasks[0]', $exception->getMessage());
        }
        self::assertSame($before, $this->fingerprint($em->getConnection()));
        $row = $em->getConnection()->fetchAssociative('SELECT task_id, completed_at FROM task_progress');
        self::assertSame($id, (int) $row['task_id']);
        self::assertSame($completedAt->format('Y-m-d H:i:s'), $row['completed_at']);
    }

    public function testOmittedTasksAreNeverDeleted(): void
    {
        [$em, $importer, $program] = $this->prepare();
        $importer->import($this->file($program));
        $task = $em->getRepository(Task::class)->findOneBy(['importKey' => 'test:go-module-reading']);
        $em->persist(new TaskProgress('demo-student', $task)); $em->flush();
        $program->learningPath->courses[0]->sprints[0]->cards[0]->tasks = [];
        $importer->import($this->file($program));
        self::assertSame(4, $em->getRepository(Task::class)->count([]));
        self::assertSame(1, $em->getRepository(TaskProgress::class)->count([]));
    }

    public function testStructuralErrorsIncludeLocationsAndDoNotWrite(): void
    {
        [$em, $importer, $program] = $this->prepare();
        $before = $this->fingerprint($em->getConnection());
        $original = json_encode($program);
        foreach (['missing', 'array-type', 'duplicate', 'position', 'url', 'unknown', 'root'] as $case) {
            $data = json_decode($original);
            switch ($case) {
                case 'missing': unset($data->learningPath->courses); break;
                case 'array-type': $data->learningPath->courses = new \stdClass(); break;
                case 'duplicate': $data->learningPath->courses[0]->externalId = $data->learningPath->externalId; break;
                case 'position': $data->learningPath->courses[0]->position = true; break;
                case 'url': $data->learningPath->courses[0]->sprints[0]->cards[0]->tasks[0]->contentUrl = 'javascript:alert(1)'; break;
                case 'unknown': $data->learningPath->unexpected = 'field'; break;
                case 'root': $data = []; break;
            }
            try {
                $importer->import($this->file($data));
                self::fail('Invalid structure: '.$case);
            } catch (ImportValidationException $exception) {
                self::assertStringContainsString('$', $exception->getMessage());
                if ($case === 'duplicate') { self::assertStringContainsString('дублирующийся ID', $exception->getMessage()); }
            }
            self::assertSame($before, $this->fingerprint($em->getConnection()));
        }
    }

    public function testMalformedJsonAndConsoleExitCodes(): void
    {
        [$em, $importer, $program] = $this->prepare();
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:learning'));
        $file = $this->file($program);
        file_put_contents($file, '{"version":');
        self::assertSame(2, $tester->execute(['file' => $file]));
        self::assertStringContainsString('Некорректный JSON', $tester->getDisplay());
        self::assertSame(0, $tester->execute(['file' => $this->file($program), '--dry-run' => true]));
        self::assertStringContainsString('Dry-run', $tester->getDisplay());
        self::assertSame(0, $em->getRepository(Task::class)->count([]));
        self::assertSame(0, $tester->execute(['file' => $this->file($program)]));
        self::assertSame(4, $em->getRepository(Task::class)->count([]));
    }

    public function testDatabaseErrorRollsBackEarlierInsertsAndUpdates(): void
    {
        [$em, $importer, $program] = $this->prepare();
        $connection = $em->getConnection();
        $before = $this->fingerprint($connection);
        $connection->executeStatement("CREATE TRIGGER fail_import_task BEFORE INSERT ON learning_task WHEN NEW.import_key = 'test:go-expenses-practice' BEGIN SELECT RAISE(ABORT, 'injected write failure'); END");
        $program->learningPath->slug = 'go-backend-developer';
        $program->learningPath->title = 'Should be rolled back';
        try {
            $importer->import($this->file($program), false, 1);
            self::fail('Injected database failure must escape');
        } catch (\Doctrine\DBAL\Exception $exception) {
            self::assertStringContainsString('injected write failure', $exception->getMessage());
        }
        self::assertSame($before, $this->fingerprint($connection));
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM learning_task'));
    }

    public function testParentMovesAndCrossTypeIdentityChangesAreRejected(): void
    {
        [$em, $importer, $program] = $this->prepare();
        $importer->import($this->file($program));
        $task = array_shift($program->learningPath->courses[0]->sprints[0]->cards[0]->tasks);
        $program->learningPath->courses[0]->sprints[1]->cards[0]->tasks[] = $task;
        try {
            $importer->import($this->file($program), true);
            self::fail('Reparenting must be explicit, not automatic');
        } catch (ImportValidationException $exception) {
            self::assertStringContainsString('другому родителю', $exception->getMessage());
        }
        $program->learningPath->externalId = 'go-module-reading';
        $program->learningPath->courses[0]->sprints[1]->cards[0]->tasks = [];
        try {
            $importer->import($this->file($program), true);
            self::fail('Changing identity type must be rejected');
        } catch (ImportValidationException $exception) {
            self::assertStringContainsString('другого типа', $exception->getMessage());
        }
    }
}
