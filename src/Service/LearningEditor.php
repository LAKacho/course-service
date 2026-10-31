<?php
namespace App\Service;
use App\Entity\LearningItem;
use Doctrine\ORM\EntityManagerInterface;
class LearningEditor
{
    public function __construct(private EntityManagerInterface $em) {}
    public function save(LearningItem $item): void { $this->em->persist($item); $this->em->flush(); }
}
