<?php
declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
/** Name-only local demo account; deliberately not production authentication. */
#[ORM\Entity, ORM\Table(name:'demo_account')]
class DemoAccount
{
    #[ORM\Id, ORM\Column(length:100)] public string $id='';
    #[ORM\Column(length:180)] public string $displayName='';
    #[ORM\Column] public bool $enabled=true;
    #[ORM\Column(type:'datetime_immutable')] public \DateTimeImmutable $createdAt;
    public function __construct(){ $this->createdAt=new \DateTimeImmutable(); }
}
