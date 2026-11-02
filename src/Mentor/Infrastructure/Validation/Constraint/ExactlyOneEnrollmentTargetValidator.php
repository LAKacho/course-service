<?php
declare(strict_types=1);
namespace App\Mentor\Infrastructure\Validation\Constraint;

use App\Mentor\Domain\Entity\Enrollment;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class ExactlyOneEnrollmentTargetValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ExactlyOneEnrollmentTarget || !$value instanceof Enrollment) {
            return;
        }
        if (!$value->hasExactlyOneTarget()) {
            $this->context->buildViolation($constraint->message)
                ->atPath($constraint->errorPath)
                ->addViolation();
        }
    }
}
