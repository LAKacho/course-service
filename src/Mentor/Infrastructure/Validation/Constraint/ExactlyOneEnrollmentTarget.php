<?php
declare(strict_types=1);
namespace App\Mentor\Infrastructure\Validation\Constraint;

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class ExactlyOneEnrollmentTarget extends Constraint
{
    public string $message = 'Выберите ровно одно: направление или курс.';
    public string $errorPath = 'learningPath';

    #[HasNamedArguments]
    public function __construct(?string $message = null, ?string $errorPath = null, ?array $groups = null, mixed $payload = null, ?array $options = null)
    {
        parent::__construct($options, $groups, $payload);
        $this->message = $message ?? $this->message;
        $this->errorPath = $errorPath ?? $this->errorPath;
    }

    public function getTargets(): string { return self::CLASS_CONSTRAINT; }
}
