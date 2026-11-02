<?php
namespace App\Access\Infrastructure\Form;
use App\Access\Application\Service\AccessChecker;
use App\Auth\Infrastructure\Session\CurrentUser;
use Doctrine\ORM\EntityManagerInterface;
final class ScopedChoices
{
    public function __construct(private EntityManagerInterface $em,private AccessChecker $access,private CurrentUser $user){}
    public function editable(string $class): array{return array_values(array_filter($this->em->getRepository($class)->findAll(),fn($item)=>$this->access->can($this->user->id(),'course.edit',$item)));}
}
