<?php
namespace App\Service\Import;

use App\Entity\{LearningPath, Course, PathCourse};
use Doctrine\ORM\EntityManagerInterface;

class ImportWriter
{
    public function __construct(private EntityManagerInterface $em) {}

    public function write(array $plan): void
    {
        $entities = [];
        foreach ($plan as $entry) {
            if ($entry['kind'] === 'path_course') {
                $entity = $entry['original'] ?? new PathCourse($entities[$entry['parentKey']]);
                $entity->course = $entities[$entry['courseKey']];
                $entity->position = $entry['position'];
            } else {
                $entity = $entry['original'] ?? new (ImportPlanner::CLASSES[$entry['kind']])();
                $entity->importKey = $entry['key'];
                foreach ($entry['fields'] as $field => $value) { $entity->{$field} = $value; }
                if (in_array($entry['kind'], ['sprint', 'card', 'task'], true)) {
                    $parentField = ['sprint' => 'course', 'card' => 'sprint', 'task' => 'card'][$entry['kind']];
                    $entity->{$parentField} = $entities[$entry['parentKey']];
                }
                if ($entry['action'] === 'updated' && ($entity instanceof LearningPath || $entity instanceof Course)) {
                    $entity->touch();
                }
            }
            $entities[$entry['key']] = $entity;
            if ($entry['action'] === 'created') { $this->em->persist($entity); }
        }
        // ImportService's wrapInTransaction flushes and commits after this method returns.
    }
}
