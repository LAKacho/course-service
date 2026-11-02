<?php
declare(strict_types=1);
namespace App\Mentor\Application\Port;
use App\Mentor\Domain\Entity\Enrollment;
interface EnrollmentValidator { public function validate(Enrollment $enrollment): ?string; }
