<?php
namespace App\Service;

use App\Entity\Enrollment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class EnrollmentService
{
    public function __construct(private EntityManagerInterface $em, private ValidatorInterface $validator, private \App\Access\AccessChecker $access, private CurrentUser $user) {}

    public function save(Enrollment $enrollment): void
    {
        $this->access->require($this->user->id(),'course.edit',$enrollment->course ?? $enrollment->learningPath);
        $errors = $this->validator->validate($enrollment);
        if (count($errors)) { throw new \DomainException((string) $errors); }
        $this->em->persist($enrollment);
        $this->em->flush();
    }
}
