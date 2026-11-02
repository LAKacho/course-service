<?php
declare(strict_types=1);
namespace App\Access\Domain\Entity;
use App\Learning\Domain\Entity\{Course, LearningPath};
class PermissionGrant
{
public ?int $id = null;
public string $userId = '';
public string $permission = '';
public string $scope = 'global';
public string $scopeKey = 'global';
public ?Course $course = null;
public ?LearningPath $learningPath = null;
public string $grantedBy = '';
public \DateTimeImmutable $createdAt;
    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }
}
