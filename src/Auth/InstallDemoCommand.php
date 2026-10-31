<?php
declare(strict_types=1);
namespace App\Auth;
use App\Access\{GrantManager,PermissionCatalog};
use App\Assessment\Service\BankInstaller;
use App\Entity\{DemoAccount,Course,Card,Enrollment};
use App\Service\ImportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface,ArrayInput};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
#[AsCommand(name:'app:demo:install',description:'Explicitly add sample programs, accounts and permission sets without purging existing data.')]
final class InstallDemoCommand extends Command
{
    public function __construct(private EntityManagerInterface $em,private ImportService $importer,private BankInstaller $bank,
        private GrantManager $grants,#[Autowire('%kernel.project_dir%')] private string $root){parent::__construct();}
    protected function execute(InputInterface $input,OutputInterface $output):int
    {
        $this->importer->import($this->root.'/examples/go-backend-developer.json',false);
        $this->importer->import($this->root.'/examples/php-backend-mini.json',false);
        $this->bank->install();
        $go=$this->em->getRepository(Course::class)->findOneBy(['importKey'=>'course-service-go:go-foundations']);
        $card=$this->em->getRepository(Card::class)->findOneBy(['importKey'=>'course-service-go:go-cli-card']);
        foreach(['demo-student'=>'Демо-ученик','local-access-manager'=>'Локальный оператор','go-editor'=>'Редактор Go','stats-reader'=>'Просмотр статистики'] as $id=>$name){
            if(!$this->em->find(DemoAccount::class,$id)){$account=new DemoAccount();$account->id=$id;$account->displayName=$name;$this->em->persist($account);}
        }$this->em->flush();
        foreach(['demo-student','local-access-manager'] as $id){
            if(!$this->em->getRepository(Enrollment::class)->count(['userId'=>$id,'course'=>$go])){$e=new Enrollment();$e->userId=$id;$e->course=$go;$this->em->persist($e);}
        }$this->em->flush();
        foreach(['course.view','task.view','task.submit'] as $permission)$this->grants->install('demo-student',$permission,'course',$go,'demo-setup');
        foreach(['assessment.take','assessment.results.view'] as $permission)$this->grants->install('demo-student',$permission,'global',null,'demo-setup');
        foreach(['course.view','course.edit','task.view','task.review'] as $permission)$this->grants->install('go-editor',$permission,'course',$go,'demo-setup');
        $this->grants->install('stats-reader','statistics.view','global',null,'demo-setup');
        foreach(array_keys(PermissionCatalog::ALL) as $permission){if($permission!=='course.delete')$this->grants->install('local-access-manager',$permission,'global',null,'demo-setup');}
        $code=$this->getApplication()->find('app:coding:install-demo')->run(new ArrayInput(['cardId'=>$card->id]),$output);
        $output->writeln('Demo ready. Logins: demo-student, local-access-manager, go-editor, stats-reader. No passwords: local use only.');
        return $code;
    }
}
