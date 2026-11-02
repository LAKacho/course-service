<?php
namespace App\Assessment\Domain\Entity;

class AnswerOption
{
public ?int $id = null;
public Question $question;
public string $code = '';
public string $text = '';
public int $position = 0;
}
