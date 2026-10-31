<?php
declare(strict_types=1);
namespace App\Auth;
use App\Entity\DemoAccount;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface,InputArgument,InputOption};
use Symfony\Component\Console\Output\OutputInterface;
#[AsCommand(name:'app:user:create',description:'Create an enabled name-only local demo account, without granting permissions.')]
final class CreateAccountCommand extends Command
{
    public function __construct(private EntityManagerInterface $em){parent::__construct();}
    protected function configure():void{$this->addArgument('login',InputArgument::REQUIRED)->addOption('name',null,InputOption::VALUE_REQUIRED,'Display name');}
    protected function execute(InputInterface $input,OutputInterface $output):int
    {
        $login=$input->getArgument('login');$name=$input->getOption('name')??$login;
        if(!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.@-]{0,99}$/D',$login)||mb_strlen($name)>180){$output->writeln('<error>Invalid login or display name.</error>');return Command::INVALID;}
        if(!$this->em->find(DemoAccount::class,$login)){$account=new DemoAccount();$account->id=$login;$account->displayName=$name;$this->em->persist($account);$this->em->flush();}
        $output->writeln('Demo account ready: '.$login.'. No permissions granted.');return Command::SUCCESS;
    }
}
