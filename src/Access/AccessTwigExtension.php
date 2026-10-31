<?php
namespace App\Access;
use App\Service\CurrentUser;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
final class AccessTwigExtension extends AbstractExtension
{
    public function __construct(private AccessChecker $access,private CurrentUser $user){}
    public function getFunctions(): array{return [
        new TwigFunction('can',fn(string $permission,?object $resource=null):bool=>$this->access->can($this->user->id(),$permission,$resource)),
        new TwigFunction('can_any',fn(string $permission):bool=>$this->access->any($this->user->id(),$permission)),
        new TwigFunction('current_user_id',fn():string=>$this->user->id())];}
}
