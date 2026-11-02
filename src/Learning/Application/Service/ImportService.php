<?php
namespace App\Learning\Application\Service;

use App\Learning\Application\Import\{ProgramValidator, ImportPlanner, ImportWriter};
use App\Learning\Application\Port\ImportStore;
use App\Learning\Application\Port\ProgramSource;

class ImportService
{
    public function __construct(
        private ProgramSource $reader,
        private ProgramValidator $validator,
        private ImportPlanner $planner,
        private ImportWriter $writer,
        private ImportStore $store,
    ) {}

    public function import(string $filename, bool $dryRun = false, ?int $pathId = null): array
    {
        $program = $this->validator->validate($this->reader->read($filename));
        if ($dryRun) {
            return $this->report($this->planner->plan($program, $pathId), true);
        }
        return $this->store->transactionForSource($program['source'], function () use ($program, $pathId): array {
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
