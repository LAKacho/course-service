<?php
namespace App\Learning\Application\Service;
use App\Learning\Domain\Entity\LearningItem;
use App\Learning\Application\Port\LearningWriteRepository;
class LearningEditor
{
    public function __construct(private LearningWriteRepository $repository) {}
    public function save(LearningItem $item): void { $this->repository->save($item); }
}
