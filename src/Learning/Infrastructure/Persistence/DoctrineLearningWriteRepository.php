<?php
declare(strict_types=1);
namespace App\Learning\Infrastructure\Persistence;
use App\Learning\Application\Port\LearningWriteRepository;
use Doctrine\ORM\EntityManagerInterface;
final class DoctrineLearningWriteRepository implements LearningWriteRepository
{
    public function __construct(private EntityManagerInterface $em) {}
    public function save(object $item): void { $this->em->persist($item);$this->em->flush(); }
}
