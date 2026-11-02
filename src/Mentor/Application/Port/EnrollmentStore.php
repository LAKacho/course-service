<?php
declare(strict_types=1);
namespace App\Mentor\Application\Port;
use App\Mentor\Domain\Entity\Enrollment;
use App\Access\Domain\Entity\PermissionGrant;
interface EnrollmentStore
{
    public function transaction(callable $operation): void;
    public function saveEnrollment(Enrollment $enrollment): void;
    public function grantExists(string $userId,string $permission,string $scopeKey): bool;
    public function saveGrants(array $grants): void;
}
