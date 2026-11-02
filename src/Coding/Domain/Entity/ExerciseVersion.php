<?php
declare(strict_types=1);
namespace App\Coding\Domain\Entity;

use App\Learning\Domain\Entity\Task;

/** Append-only exercise specification; hidden tests never enter learner projections. */
class ExerciseVersion
{
    public ?int $id = null;
    public Task $task;
    public int $version = 1;
    public string $testsVersion = '';
    public string $title = '';
    public string $statement = '';
    public array $starters = [];
    public array $limits = [];
    public array $publicTests = [];
    public array $hiddenTests = [];
    public string $criteria = 'all_tests_pass';
    public \DateTimeImmutable $createdAt;
    public function __construct() { $this->createdAt=new \DateTimeImmutable(); }
}
