<?php
declare(strict_types=1);
namespace App\Auth\Infrastructure\Console;
use App\Access\Application\Service\GrantManager;
use App\Access\Domain\PermissionCatalog;
use App\Assessment\Application\Service\BankInstaller;
use App\Auth\Domain\Entity\DemoAccount;
use App\Learning\Domain\Entity\Course;
use App\Learning\Domain\Entity\Card;
use App\Mentor\Domain\Entity\Enrollment;
use App\Learning\Application\Service\ImportService;
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
        foreach(['001'=>'Р”РµРјРѕ-СѓС‡РµРЅРёРє','002'=>'Р РµРґР°РєС‚РѕСЂ Go','003'=>'РџСЂРѕСЃРјРѕС‚СЂ СЃС‚Р°С‚РёСЃС‚РёРєРё','004'=>'РћРїРµСЂР°С‚РѕСЂ РґРѕСЃС‚СѓРїР°'] as $id=>$name){
            if(!$this->em->find(DemoAccount::class,$id)){$account=new DemoAccount();$account->id=$id;$account->displayName=$name;$this->em->persist($account);}
        }$this->em->flush();
        foreach(['001','004'] as $id){
            if(!$this->em->getRepository(Enrollment::class)->count(['userId'=>$id,'course'=>$go])){$e=new Enrollment();$e->userId=$id;$e->course=$go;$this->em->persist($e);}
        }$this->em->flush();
        foreach(['course.view','task.view','task.submit'] as $permission)$this->grants->install('001',$permission,'course',$go,'demo-setup');
        foreach(['assessment.take','assessment.results.view'] as $permission)$this->grants->install('001',$permission,'global',null,'demo-setup');
        foreach(['course.view','course.edit','task.view','task.review'] as $permission)$this->grants->install('002',$permission,'course',$go,'demo-setup');
        $this->grants->install('003','statistics.view','global',null,'demo-setup');
        foreach(array_keys(PermissionCatalog::ALL) as $permission){if($permission!=='course.delete')$this->grants->install('004',$permission,'global',null,'demo-setup');}
        $code=$this->getApplication()->find('app:coding:install-demo')->run(new ArrayInput(['cardId'=>$card->id]),$output);
        $output->writeln('Demo ready. Logins: 001 (СѓС‡РµРЅРёРє), 002 (СЂРµРґР°РєС‚РѕСЂ Go), 003 (СЃС‚Р°С‚РёСЃС‚РёРєР°), 004 (РѕРїРµСЂР°С‚РѕСЂ РґРѕСЃС‚СѓРїР°). No passwords: local use only.');
        return $code;
    }
}


\n