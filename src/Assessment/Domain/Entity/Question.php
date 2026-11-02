<?php
namespace App\Assessment\Domain\Entity;

class Question
{
public ?int $id = null;
public string $code = '';
public int $version = 1;
public string $type = 'single_choice';
public string $text = '';
public ?string $codeSnippet = null;
public ?string $language = null;
public string $difficulty = 'beginner';
public int $weight = 1;
public int $position = 0;
    /** Private bank key: never serialize this entity to a student. */
public array $correctAnswer = [];
public string $explanation = '';
public string $sourceHash = '';
    public iterable $skills = [];
}
