<?php
declare(strict_types=1);
namespace App\Assessment\Infrastructure\Persistence;
use App\Assessment\Application\Port\AssessmentRepository;
use App\Assessment\Domain\Entity\{AnswerOption,Assessment,AssessmentAttempt,AssessmentResult,Question,Skill,SkillAssessment,UserAnswer};
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Common\Collections\ArrayCollection;
final class DoctrineAssessmentRepository implements AssessmentRepository
{
    public function __construct(private EntityManagerInterface $em) {}
    public function transaction(callable $operation): mixed { return $this->em->wrapInTransaction($operation); }
    public function lock(object $entity): void { $this->em->lock($entity,LockMode::PESSIMISTIC_WRITE); }
    public function refresh(object $entity): void { $this->em->refresh($entity); }
    public function catalog(): array { return $this->em->getRepository(Assessment::class)->findBy([],['id'=>'ASC']); }
    public function ownedAttempt(string $publicId,string $userId): ?AssessmentAttempt
    { return $this->em->getRepository(AssessmentAttempt::class)->findOneBy(['publicId'=>$publicId,'userId'=>$userId]); }
    public function activeAttempt(Assessment $assessment,string $userId): ?AssessmentAttempt
    { return $this->em->getRepository(AssessmentAttempt::class)->findOneBy(['assessment'=>$assessment,'userId'=>$userId,'status'=>'in_progress']); }
    public function answers(AssessmentAttempt $attempt): array
    { return $this->em->getRepository(UserAnswer::class)->findBy(['attempt'=>$attempt],['id'=>'ASC']); }
    public function question(int $id): ?Question { return $this->em->find(Question::class,$id); }
    public function skill(int $id): ?Skill { return $this->em->find(Skill::class,$id); }
    public function options(Question $question): array
    { return $this->em->getRepository(AnswerOption::class)->findBy(['question'=>$question],['position'=>'ASC','id'=>'ASC']); }
    public function result(AssessmentAttempt $attempt): ?AssessmentResult
    { return $this->em->getRepository(AssessmentResult::class)->findOneBy(['attempt'=>$attempt]); }
    public function skillByCode(string $code): ?Skill { return $this->em->getRepository(Skill::class)->findOneBy(['code'=>$code]); }
    public function questionByCode(string $code): ?Question { return $this->em->getRepository(Question::class)->findOneBy(['code'=>$code]); }
    public function assessmentByCode(string $code): ?Assessment { return $this->em->getRepository(Assessment::class)->findOneBy(['code'=>$code]); }
    public function addQuestionToAssessment(Assessment $assessment, Question $question): void
    { $this->addToCollection($assessment, 'questions', $question); }
    public function addSkillToQuestion(Question $question, Skill $skill): void
    { $this->addToCollection($question, 'skills', $skill); }
    public function skillResults(AssessmentResult $result): array
    { return $this->em->getRepository(SkillAssessment::class)->findBy(['result'=>$result],['weightedPercent'=>'ASC','skillCode'=>'ASC']); }
    public function persist(object $entity): void { $this->em->persist($entity); }

    private function addToCollection(object $entity, string $property, object $item): void
    {
        $items = $entity->{$property};
        if (is_array($items)) { $items = new ArrayCollection($items); }
        if (!$items->contains($item)) { $items->add($item); }
        $entity->{$property} = $items;
    }
}
