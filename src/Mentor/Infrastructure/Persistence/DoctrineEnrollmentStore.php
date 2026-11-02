<?php
declare(strict_types=1);
namespace App\Mentor\Infrastructure\Persistence;
use App\Mentor\Domain\Entity\Enrollment;
use App\Access\Domain\Entity\PermissionGrant;
use App\Mentor\Application\Port\EnrollmentStore;
use Doctrine\ORM\EntityManagerInterface;
final class DoctrineEnrollmentStore implements EnrollmentStore
{
    public function __construct(private EntityManagerInterface $em) {}
    public function transaction(callable $operation): void { $this->em->wrapInTransaction($operation); }
    public function saveEnrollment(Enrollment $enrollment): void { $this->em->persist($enrollment); $this->em->flush(); }
    public function grantExists(string $userId,string $permission,string $scopeKey): bool
    { return (bool)$this->em->getRepository(PermissionGrant::class)->count(['userId'=>$userId,'permission'=>$permission,'scopeKey'=>$scopeKey]); }
    public function saveGrants(array $grants): void { foreach($grants as $grant)$this->em->persist($grant); $this->em->flush(); }
}
