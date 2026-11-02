<?php
declare(strict_types=1);
namespace App\Access\Domain;
/** Transport-neutral policy data; external adapters need not return Doctrine entities. */
final readonly class GrantDefinition
{
    public function __construct(public string $permission,public string $scope,public ?int $resourceId=null){}
}
