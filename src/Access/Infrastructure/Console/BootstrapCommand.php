<?php
declare(strict_types=1);
namespace App\Access\Infrastructure\Console;
use App\Access\Application\Service\GrantManager;
use App\Access\Domain\PermissionCatalog;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument,InputOption,InputInterface};
use Symfony\Component\Console\Output\OutputInterface;
#[AsCommand(name:'app:access:bootstrap',description:'Explicit local operator bootstrap. Never grants rights automatically on login.')]
final class BootstrapCommand extends Command
{
    public function __construct(private GrantManager $manager){parent::__construct();}
    protected function configure(): void{$this->addArgument('userId',InputArgument::REQUIRED)->addOption('manage',null,InputOption::VALUE_NONE,'Also grant access.manage; trusted local operator only.');}
    protected function execute(InputInterface $input,OutputInterface $output): int
    {
        foreach(array_keys(PermissionCatalog::ALL) as $permission){
            if($permission==='course.delete' || $permission==='access.manage' && !$input->getOption('manage'))continue;
            $this->manager->install($input->getArgument('userId'),$permission,'global',null,'local-cli');
        }
        $output->writeln('Explicit local grants installed for '.$input->getArgument('userId').'. New users still have no grants.');return Command::SUCCESS;
    }
}
