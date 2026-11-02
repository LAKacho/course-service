<?php
declare(strict_types=1);
namespace App\Mentor\Application\Service;

use App\Access\Application\Service\AccessChecker;
use App\Mentor\Domain\Entity\Enrollment;
use App\Access\Domain\Entity\PermissionGrant;
use App\Mentor\Application\Port\{EnrollmentStore,EnrollmentValidator};
use App\Shared\Application\Port\CurrentUserId;

final class EnrollmentService
{
    public function __construct(private EnrollmentStore $store,private EnrollmentValidator $validator,
        private AccessChecker $access,private CurrentUserId $user) {}

    public function save(Enrollment $enrollment): void
    {
        $actor=$this->user->id();
        $this->access->require($actor,'course.edit',$enrollment->course ?? $enrollment->learningPath);
        $errors=$this->validator->validate($enrollment);
        if($errors!==null)throw new \DomainException($errors);
        $this->store->transaction(function() use($enrollment,$actor): void {
            $this->store->saveEnrollment($enrollment);
            if($enrollment->userId===$actor)return;
            $scope=$enrollment->course?'course':'learning_path';
            $resource=$enrollment->course ?? $enrollment->learningPath;
            $scopeKey=$scope.':'.$resource->id;
            $grants=[];
            foreach(['course.view','task.view','task.submit'] as $permission){
                if($this->store->grantExists($enrollment->userId,$permission,$scopeKey))continue;
                $grant=new PermissionGrant();$grant->userId=$enrollment->userId;$grant->permission=$permission;
                $grant->scope=$scope;$grant->scopeKey=$scopeKey;$grant->grantedBy=$actor;
                if($enrollment->course)$grant->course=$enrollment->course;else $grant->learningPath=$enrollment->learningPath;
                $grants[]=$grant;
            }
            $this->store->saveGrants($grants);
        });
    }
}
