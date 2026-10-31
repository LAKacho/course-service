<?php
namespace App\Repository;

use App\Entity\{LearningPath, PathCourse};
use Doctrine\ORM\EntityManagerInterface;

class LearningPathRepository
{
    public function __construct(private EntityManagerInterface $em, private \App\Access\AccessChecker $access, private \App\Access\ResourcePolicy $policy, private \App\Service\CurrentUser $user) {}

    public function all(): array
    {
        return array_values(array_filter($this->em->getRepository(LearningPath::class)->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC']), fn($path)=>$this->access->pathVisible($this->user->id(),$path)));
    }

    /** @return list<PathCourse> */
    public function courses(LearningPath $path): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('link', 'course')->from(PathCourse::class, 'link')
            ->join('link.course', 'course')
            ->where('link.path = :path')->setParameter('path', $path)
            ->orderBy('link.position', 'ASC')->addOrderBy('link.id', 'ASC')
            ->getQuery()->getResult();
        return array_values(array_filter($rows,fn($link)=>$this->policy->courseVisible($this->user->id(),$link->course)));
    }

    public function link(LearningPath $path, int $id): ?PathCourse
    {
        return $this->em->getRepository(PathCourse::class)->findOneBy(['id' => $id, 'path' => $path]);
    }
}
