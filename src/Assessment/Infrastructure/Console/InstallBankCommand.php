<?php
namespace App\Assessment\Infrastructure\Console;
use App\Assessment\Application\Service\BankInstaller;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:assessment:install-bank', description: 'Добавить оригинальный банк входной диагностики без удаления данных')]
class InstallBankCommand extends Command
{
    public function __construct(private BankInstaller $installer) { parent::__construct(); }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $counts = $this->installer->install(); $io = new SymfonyStyle($input, $output);
        $io->success(sprintf('Добавлено: компетенций %d, вопросов %d, тестирований %d.', $counts['skills'], $counts['questions'], $counts['assessments']));
        return Command::SUCCESS;
    }
}
