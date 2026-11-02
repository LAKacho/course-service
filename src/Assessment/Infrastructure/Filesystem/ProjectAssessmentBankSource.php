<?php
declare(strict_types=1);
namespace App\Assessment\Infrastructure\Filesystem;
use App\Assessment\Application\Port\AssessmentBankSource;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
final class ProjectAssessmentBankSource implements AssessmentBankSource
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private string $root) {}
    public function read(): array
    { return json_decode(file_get_contents($this->root.'/resources/entry-assessment-bank.json'),true,64,JSON_THROW_ON_ERROR); }
}
