<?php
namespace App\Service;

use App\Service\Import\{ProgramReader, ProgramValidator, ImportPlanner, ImportWriter};
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;

class ImportService
{
    public function __construct(
        private ProgramReader $reader,
        private ProgramValidator $validator,
        private ImportPlanner $planner,
        private ImportWriter $writer,
        private EntityManagerInterface $em,
    ) {}

    public function import(string $filename, bool $dryRun = false, ?int $pathId = null): array
    {
        $program = $this->validator->validate($this->reader->read($filename));
        if ($dryRun) {
            return $this->report($this->planner->plan($program, $pathId), true);
        }
        return $this->em->wrapInTransaction(function () use ($program, $pathId): array {
            if ($this->em->getConnection()->getDatabasePlatform() instanceof PostgreSQLPlatform) {
                // Serialize importers of the same source; unique indexes also guard identity.
                $this->em->getConnection()->executeQuery('SELECT pg_advisory_xact_lock(hashtext(:source))', ['source' => $program['source']]);
            }
            $plan = $this->planner->plan($program, $pathId, true);
            $this->writer->write($plan);
            return $this->report($plan, false);
        });
    }

    private function report(array $plan, bool $dryRun): array
    {
        $counts = [];
        $details = [];
        foreach (['path', 'course', 'sprint', 'card', 'task', 'path_course'] as $kind) {
            $counts[$kind] = ['created' => 0, 'updated' => 0, 'unchanged' => 0];
        }
        foreach ($plan as $entry) {
            ++$counts[$entry['kind']][$entry['action']];
            $details[] = ['kind' => $entry['kind'], 'externalId' => $entry['externalId'], 'action' => $entry['action'], 'changes' => $entry['changes']];
        }
        return ['dryRun' => $dryRun, 'counts' => $counts, 'details' => $details];
    }
}
