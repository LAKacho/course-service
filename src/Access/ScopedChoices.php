<?php
namespace App\Access;
use App\Service\CurrentUser;
use Doctrine\ORM\EntityManagerInterface;
final class ScopedChoices
{
    public function __construct(private EntityManagerInterface $em,private AccessChecker $access,private CurrentUser $user){}
    public function editable(string $class): array{return array_values(array_filter($this->em->getRepository($class)->findAll(),fn($item)=>$this->access->can($this->user->id(),'course.edit',$item)));}
}
