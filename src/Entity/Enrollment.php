<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'unique_enrollment_path', columns: ['user_id', 'learning_path_id'])]
#[ORM\UniqueConstraint(name: 'unique_enrollment_course', columns: ['user_id', 'course_id'])]
#[UniqueEntity(fields: ['userId', 'learningPath'], message: 'Направление уже назначено этому ученику.')]
#[UniqueEntity(fields: ['userId', 'course'], message: 'Курс уже назначен этому ученику.')]
class Enrollment
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(normalizer: 'trim'), Assert\Length(max: 100)]
    public string $userId = 'demo-student';

    #[ORM\ManyToOne(targetEntity: LearningPath::class), ORM\JoinColumn(nullable: true)]
    public ?LearningPath $learningPath = null;

    #[ORM\ManyToOne(targetEntity: Course::class), ORM\JoinColumn(nullable: true)]
    public ?Course $course = null;

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $createdAt;

    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }

    #[Assert\Callback]
    public function validateTarget(ExecutionContextInterface $context): void
    {
        if (($this->learningPath === null) === ($this->course === null)) {
            $context->buildViolation('Выберите ровно одно: направление или курс.')->atPath('learningPath')->addViolation();
        }
    }
}
