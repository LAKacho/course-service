<?php
namespace App\DataFixtures;

use App\Learning\Domain\Entity\LearningPath;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class LearningPathFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        if ($manager->getRepository(LearningPath::class)->findOneBy(['slug' => 'go-backend-developer'])) {
            return;
        }
        $path = new LearningPath();
        $path->title = 'Go Backend Developer';
        $path->slug = 'go-backend-developer';
        $path->description = 'Направление для будущего изучения серверной разработки на Go. Курсы будут добавляться постепенно.';
        $path->status = 'draft';
        $manager->persist($path);
        $manager->flush();
    }
}
