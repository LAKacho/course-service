<?php
declare(strict_types=1);
namespace App\Coding\Service;
use App\Coding\Entity\ExerciseVersion;
use App\Entity\{Task, TaskProgress};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;

final class ExerciseService
{
    public function __construct(private EntityManagerInterface $em) {}
    public function createVersion(Task $task, string $title, string $statement, array $starters, array $publicTests, array $hiddenTests): ExerciseVersion
    {
        if (trim($title) === '' || mb_strlen($title) > 180 || trim($statement) === '' || mb_strlen($statement) > 20000
            || !$starters || array_diff(array_keys($starters), ['php', 'go'])) { throw new \InvalidArgumentException('Проверьте название, условие и языки задания.'); }
        foreach ($starters as $source) { if (!is_string($source) || trim($source) === '' || strlen($source) > 65536) { throw new \InvalidArgumentException('Стартовый код обязателен и ограничен 64 КиБ.'); } }
        $ids = [];
        foreach ([$publicTests, $hiddenTests] as $tests) {
            if (!array_is_list($tests) || count($tests) < 1 || count($tests) > 50) { throw new \InvalidArgumentException('Нужно от 1 до 50 публичных и скрытых тестов.'); }
            foreach ($tests as $test) {
                if (!is_array($test) || array_diff(array_keys($test), ['id', 'input', 'expected']) || !isset($test['id']) || !is_string($test['id'])
                    || !preg_match('/^[a-zA-Z0-9_-]{1,40}$/D', $test['id']) || isset($ids[$test['id']]) || !array_key_exists('input', $test) || !array_key_exists('expected', $test)) {
                    throw new \InvalidArgumentException('Тест: уникальный id, input и expected; дополнительные поля запрещены.');
                }
                $ids[$test['id']] = true;
            }
        }
        if (strlen(json_encode([$publicTests, $hiddenTests], JSON_THROW_ON_ERROR)) > 100000) { throw new \InvalidArgumentException('Слишком большой набор тестов.'); }
        return $this->em->wrapInTransaction(function () use ($task, $title, $statement, $starters, $publicTests, $hiddenTests): ExerciseVersion {
            $this->em->lock($task, LockMode::PESSIMISTIC_WRITE);
            if ($task->type !== 'coding' && $this->em->getRepository(TaskProgress::class)->count(['task' => $task])) {
                throw new \DomainException('Нельзя преобразовать уже выполненное задание: создайте новое в существующей карточке.');
            }
            $previous = $this->em->getRepository(ExerciseVersion::class)->findOneBy(['task' => $task], ['version' => 'DESC']);
            $version = new ExerciseVersion(); $version->task = $task; $version->version = ($previous?->version ?? 0) + 1;
            $version->title = trim($title); $version->statement = $statement; $version->starters = $starters;
            $version->publicTests = $publicTests; $version->hiddenTests = $hiddenTests;
            $version->limits = ['timeMs' => 2000, 'memoryMb' => 128, 'processes' => 16, 'outputBytes' => 16384, 'sourceBytes' => 65536, 'network' => false];
            $version->testsVersion = hash('sha256', json_encode([$publicTests, $hiddenTests, $version->criteria], JSON_THROW_ON_ERROR));
            $task->type = 'coding'; $this->em->persist($version);
            return $version;
        });
    }
}
