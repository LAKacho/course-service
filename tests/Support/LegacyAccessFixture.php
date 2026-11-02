<?php
namespace App\Tests\Support;
use App\Access\Domain\Entity\PermissionGrant;
use App\Access\PermissionCatalog;
use Doctrine\ORM\EntityManagerInterface;
/** Explicit permissions for pre-PBAC regression fixtures only, not a production default. */
final class LegacyAccessFixture
{
    public static function install(EntityManagerInterface $em): void
    {
        foreach(array_keys(PermissionCatalog::ALL) as $permission){
            $grant=new PermissionGrant();$grant->userId='001';$grant->permission=$permission;$grant->grantedBy='phpunit-fixture';$em->persist($grant);
        }$em->flush();
    }
}
