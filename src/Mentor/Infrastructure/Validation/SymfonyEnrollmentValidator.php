<?php
declare(strict_types=1);
namespace App\Mentor\Infrastructure\Validation;
use App\Mentor\Domain\Entity\Enrollment;
use App\Mentor\Application\Port\EnrollmentValidator;
use Symfony\Component\Validator\Validator\ValidatorInterface;
final class SymfonyEnrollmentValidator implements EnrollmentValidator
{
    public function __construct(private ValidatorInterface $validator) {}
    public function validate(Enrollment $enrollment): ?string
    { $errors=$this->validator->validate($enrollment); return count($errors)?(string)$errors:null; }
}
