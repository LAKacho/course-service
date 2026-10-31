<?php
namespace App\Service\Import;

class ImportValidationException extends \RuntimeException
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode("\n", $errors));
    }
}
