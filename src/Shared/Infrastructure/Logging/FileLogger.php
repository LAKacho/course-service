<?php
namespace App\Shared\Infrastructure\Logging;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Log\Logger;
class FileLogger extends Logger
{
    public function __construct(#[Autowire('%kernel.logs_dir%/%kernel.environment%.log')] string $path) {
        if (!is_dir(dirname($path))) { mkdir(dirname($path), 0775, true); }
        parent::__construct('warning', $path);
    }
}
