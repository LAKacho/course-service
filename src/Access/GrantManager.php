<?php
declare(strict_types=1);
namespace App\Access;
use App\Entity\{PermissionGrant,Course,LearningPath};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
final class GrantManager
{
    public function __construct(private EntityManagerInterface $em,private AccessChecker $access) {}
    public function grant(string $actor,string $user,string $permission,string $scope,?object $resource): void
    {
        $this->access->require($actor,'access.manage');
        if($actor===$user || $permission==='access.manage') throw new AccessDeniedHttpException('Самостоятельная выдача прав и делегирование access.manage через веб запрещены.');
        if(!$this->access->can($actor,$permission,$resource)) throw new AccessDeniedHttpException('Нельзя делегировать разрешение вне собственной области.');
        $this->install($user,$permission,$scope,$resource,$actor);
    }
    public function revoke(string $actor,PermissionGrant $grant): void
    {
        $this->access->require($actor,'access.manage');
        $resource=$grant->course ?? $grant->learningPath;
        if($actor===$grant->userId || $grant->permission==='access.manage' || !$this->access->can($actor,$grant->permission,$resource)) throw new AccessDeniedHttpException('Изменение этого назначения запрещено.');
        $this->em->remove($grant); $this->em->flush();
    }
    /** Privileged CLI/bootstrap only, never called with unvalidated HTTP values. */
    public function install(string $user,string $permission,string $scope,?object $resource,string $actor): void
    {
        if(!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.@-]{0,99}$/D',$user) || !PermissionCatalog::validScope($permission,$scope)
            || $scope==='global' && $resource!==null || $scope==='course' && !$resource instanceof Course
            || $scope==='learning_path' && !$resource instanceof LearningPath) throw new \InvalidArgumentException('Некорректное назначение разрешения.');
        if($resource && !$resource->id)throw new \InvalidArgumentException('Ресурс должен существовать.');
        $key=$scope==='global'?'global':$scope.':'.$resource->id;
        if($this->em->getRepository(PermissionGrant::class)->count(['userId'=>$user,'permission'=>$permission,'scopeKey'=>$key])) return;
        $grant=new PermissionGrant();$grant->userId=$user;$grant->permission=$permission;$grant->scope=$scope;$grant->scopeKey=$key;$grant->grantedBy=$actor;
        if($resource instanceof Course)$grant->course=$resource;
        if($resource instanceof LearningPath)$grant->learningPath=$resource;
        $this->em->persist($grant);$this->em->flush();
    }
}
