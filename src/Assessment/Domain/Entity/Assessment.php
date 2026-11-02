<?php
namespace App\Assessment\Domain\Entity;

class Assessment
{
public ?int $id = null;
public string $code = '';
public string $title = '';
public string $description = '';
public int $version = 1;
public array $coreSkills = [];
    public iterable $questions = [];
}
