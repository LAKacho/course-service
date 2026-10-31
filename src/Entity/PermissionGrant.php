<?php
declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity, ORM\Table(name: 'permission_grant')]
#[ORM\UniqueConstraint(name: 'unique_permission_assignment', columns: ['user_id','permission','scope_key'])]
class PermissionGrant
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] public ?int $id = null;
    #[ORM\Column(length:100)] public string $userId = '';
    #[ORM\Column(length:60)] public string $permission = '';
    #[ORM\Column(length:20)] public string $scope = 'global';
    #[ORM\Column(length:50)] public string $scopeKey = 'global';
    #[ORM\ManyToOne(targetEntity: Course::class), ORM\JoinColumn(nullable:true)] public ?Course $course = null;
    #[ORM\ManyToOne(targetEntity: LearningPath::class), ORM\JoinColumn(nullable:true)] public ?LearningPath $learningPath = null;
    #[ORM\Column(length:100)] public string $grantedBy = '';
    #[ORM\Column(type:'datetime_immutable')] public \DateTimeImmutable $createdAt;
    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }
}
