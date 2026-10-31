<?php
namespace App\Service\Import;

use App\Entity\{LearningPath, Course, Sprint, Card, Task, TaskProgress, PathCourse};
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;

class ImportPlanner
{
    public const CLASSES = ['path' => LearningPath::class, 'course' => Course::class, 'sprint' => Sprint::class, 'card' => Card::class, 'task' => Task::class];

    public function __construct(private EntityManagerInterface $em) {}

    /** No managed entities are changed here; dry-run uses detached copies only. */
    public function plan(array $program, ?int $pathId = null, bool $lockTasks = false): array
    {
        $entries = [];
        $prototypes = [];
        $errors = [];
        foreach ($program['nodes'] as $node) {
            $class = self::CLASSES[$node['kind']];
            foreach (self::CLASSES as $otherClass) {
                if ($otherClass !== $class && $this->em->getRepository($otherClass)->findOneBy(['importKey' => $node['key']])) {
                    $errors[] = $node['location'].': внешний ID '.$node['externalId'].' уже используется элементом другого типа.';
                }
            }
            $original = $this->em->getRepository($class)->findOneBy(['importKey' => $node['key']]);
            if ($node['kind'] === 'path' && $pathId !== null) {
                $bound = $this->em->find(LearningPath::class, $pathId);
                if (!$bound) {
                    $errors[] = '--path-id: направление не найдено.';
                } elseif (($bound->importKey !== null && $bound->importKey !== $node['key']) || ($original && $original->id !== $bound->id)) {
                    $errors[] = '--path-id: направление или внешний ID уже привязаны к другой записи.';
                } else {
                    $original = $bound;
                }
            }
            if ($original instanceof Task && $lockTasks && $this->em->getConnection()->getDatabasePlatform() instanceof PostgreSQLPlatform) {
                // Shares the task row lock used by ProgressService before checking completion.
                $this->em->lock($original, LockMode::PESSIMISTIC_WRITE);
                $this->em->refresh($original);
            }
            $prototype = $original ? clone $original : new $class();
            $changes = [];
            foreach ($node['fields'] as $field => $value) {
                if (!$original || $original->{$field} !== $value) { $changes[] = $field; }
                $prototype->{$field} = $value;
            }
            if ($prototype->importKey !== $node['key']) { $changes[] = 'importKey'; }
            $prototype->importKey = $node['key'];
            if (in_array($node['kind'], ['sprint', 'card', 'task'], true)) {
                $parentField = ['sprint' => 'course', 'card' => 'sprint', 'task' => 'card'][$node['kind']];
                $parent = $prototypes[$node['parentKey']];
                if ($original && $original->{$parentField}->id !== $parent->id) {
                    $errors[] = $node['location'].': внешний ID '.$node['externalId'].' уже принадлежит другому родителю; автоматический перенос запрещён.';
                }
                $prototype->{$parentField} = $parent;
            }
            if ($prototype instanceof LearningPath) {
                $conflict = $this->em->getRepository(LearningPath::class)->findOneBy(['slug' => $prototype->slug]);
                if ($conflict && (!$original || $conflict->id !== $original->id)) {
                    $errors[] = $node['location'].'.slug: адрес уже занят направлением №'.$conflict->id.'. Для явной привязки используйте --path-id='.$conflict->id.'; сопоставления по названию нет.';
                }
            }
            if ($original instanceof Task && $changes && $this->em->getRepository(TaskProgress::class)->findOneBy(['task' => $original])) {
                $errors[] = $node['location'].': задание '.$node['externalId'].' имеет прогресс ученика; изменение полей '.implode(', ', $changes).' запрещено. Сохраните задание без изменений или создайте новое с новым externalId.';
            }
            $prototypes[$node['key']] = $prototype;
            $entries[$node['key']] = $node + ['original' => $original, 'action' => !$original ? 'created' : ($changes ? 'updated' : 'unchanged'), 'changes' => $changes];
        }
        $rootKey = $program['nodes'][0]['key'];
        foreach ($program['nodes'] as $node) {
            if ($node['kind'] !== 'course') { continue; }
            $path = $prototypes[$rootKey];
            $course = $prototypes[$node['key']];
            $link = $path->id !== null && $course->id !== null
                ? $this->em->getRepository(PathCourse::class)->findOneBy(['path' => $path->id, 'course' => $course->id])
                : null;
            $action = !$link ? 'created' : ($link->position !== $node['position'] ? 'updated' : 'unchanged');
            $entries['link:'.$node['key']] = [
                'kind' => 'path_course', 'key' => 'link:'.$node['key'], 'externalId' => $node['externalId'],
                'parentKey' => $rootKey, 'courseKey' => $node['key'], 'position' => $node['position'],
                'original' => $link, 'action' => $action, 'fields' => [], 'location' => $node['location'],
                'changes' => $action === 'unchanged' ? [] : ['position'],
            ];
            if ($action !== 'unchanged' && $entries[$rootKey]['action'] === 'unchanged') {
                $entries[$rootKey]['action'] = 'updated';
                $entries[$rootKey]['changes'][] = 'courseLinks';
            }
        }
        if ($errors) { throw new ImportValidationException($errors); }
        return array_values($entries);
    }
}
