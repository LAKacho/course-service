<?php
declare(strict_types=1);
namespace App\Learning\Application\Import;

class ImportValidationException extends \RuntimeException
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode("\n", $errors));
    }
}
