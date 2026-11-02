<?php
namespace App\Learning\Domain\Entity;
abstract class LearningItem
{
    // Stable source:externalId identity; legacy/manual records remain unbound (NULL).
    public ?string $importKey = null;
    public ?int $id = null;
    public string $title = '';
    public string $description = '';
}
