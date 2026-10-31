<?php
declare(strict_types=1);
namespace App\Access;
use App\Entity\PermissionGrant;
use Doctrine\ORM\EntityManagerInterface;
final class LocalGrantSource implements GrantSource
{
    public function __construct(private EntityManagerInterface $em) {}
    public function forUser(string $userId): array
    {
        $result=[];
        foreach($this->em->getRepository(PermissionGrant::class)->findBy(['userId'=>$userId]) as $grant){
            if($grant->scope==='global' && !$grant->course && !$grant->learningPath)$result[]=new GrantDefinition($grant->permission,'global');
            elseif($grant->scope==='course' && $grant->course && !$grant->learningPath)$result[]=new GrantDefinition($grant->permission,'course',$grant->course->id);
            elseif($grant->scope==='learning_path' && $grant->learningPath && !$grant->course)$result[]=new GrantDefinition($grant->permission,'learning_path',$grant->learningPath->id);
        }
        return $result;
    }
}
