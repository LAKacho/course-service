<?php
declare(strict_types=1);
namespace App\Auth\Domain\Entity;

/** Name-only local demo account; deliberately not production authentication. */
class DemoAccount
{
    public string $id = '';
    public string $displayName = '';
    public bool $enabled = true;
    public \DateTimeImmutable $createdAt;

    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }
}
