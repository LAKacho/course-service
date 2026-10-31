<?php
declare(strict_types=1);
namespace App\Coding\Entity;
use App\Entity\Task;
use Doctrine\ORM\Mapping as ORM;

/** Append-only versions; never serialize this entity to a learner. */
#[ORM\Entity, ORM\Table(name: 'coding_exercise_version')]
#[ORM\UniqueConstraint(name: 'unique_coding_task_version', columns: ['task_id', 'version'])]
class ExerciseVersion
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] public ?int $id = null;
    #[ORM\ManyToOne(targetEntity: Task::class), ORM\JoinColumn(nullable: false)] public Task $task;
    #[ORM\Column] public int $version = 1;
    #[ORM\Column(length: 64)] public string $testsVersion = '';
    #[ORM\Column(length: 180)] public string $title = '';
    #[ORM\Column(type: 'text')] public string $statement = '';
    /** language => starter source; no automatic execution. */
    #[ORM\Column(type: 'json')] public array $starters = [];
    #[ORM\Column(type: 'json')] public array $limits = [];
    #[ORM\Column(type: 'json')] public array $publicTests = [];
    #[ORM\Column(type: 'json')] public array $hiddenTests = [];
    #[ORM\Column(length: 40)] public string $criteria = 'all_tests_pass';
    #[ORM\Column(type: 'datetime_immutable')] public \DateTimeImmutable $createdAt;
    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }
}
