<?php
declare(strict_types=1);
namespace App\Coding\Command;
use App\Coding\Service\ExerciseService;
use App\Entity\{Card, Task};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputArgument};
use Symfony\Component\Console\Output\OutputInterface;
#[AsCommand(name: 'app:coding:install-demo', description: 'Append a small original PHP/Go task to an explicitly selected existing card; never assign courses.')]
final class InstallDemoCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, private ExerciseService $exercises) { parent::__construct(); }
    protected function configure(): void { $this->addArgument('cardId', InputArgument::REQUIRED, 'Existing card ID'); }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $card = $this->em->find(Card::class, (int) $input->getArgument('cardId'));
        if (!$card) { $output->writeln('<error>Card not found.</error>'); return Command::INVALID; }
        $task = $this->em->getRepository(Task::class)->findOneBy(['importKey' => 'coding-demo:sum-v1']);
        if (!$task) {
            $task = $this->em->wrapInTransaction(function () use ($card): Task {
                $task = new Task(); $task->card = $card; $task->importKey = 'coding-demo:sum-v1'; $task->title = 'Сумма двух чисел';
                $task->description = 'Практика PHP/Go: прочитайте JSON-массив и выведите сумму. Решение сохраняется; проверка требует отдельного runner.';
                $task->position = 1000; $this->em->persist($task); $this->em->flush();
                $php = <<<'CODE'
<?php
declare(strict_types=1);

// Вход: JSON-массив из двух целых чисел, например [2, 3].
$numbers = json_decode(trim(fgets(STDIN)), true);

// TODO: вычислите сумму и выведите её как JSON-число.
$result = 0;
echo json_encode($result), PHP_EOL;
CODE;
                $go = <<<'CODE'
package main

import (
    "encoding/json"
    "fmt"
    "os"
)

func main() {
    var numbers []int
    json.NewDecoder(os.Stdin).Decode(&numbers)
    // TODO: вычислите сумму и выведите её как JSON-число.
    result := 0
    fmt.Println(result)
}
CODE;
                $this->exercises->createVersion($task, $task->title,
                    "Программа получает через стандартный ввод одну строку JSON: массив из двух целых чисел. Выведите их сумму как JSON-число и перевод строки.\n\nКаждое число находится в диапазоне от −100000 до 100000. Вход корректен. Не добавляйте пояснения к выводу. Выберите PHP или Go.\n\nПример: [2, 3] → 5. Это оригинальная вводная задача для знакомства с редактором.",
                    ['php' => $php, 'go' => $go],
                    [['id'=>'example_sum','input'=>[2,3],'expected'=>5],['id'=>'example_zero','input'=>[0,0],'expected'=>0]],
                    [['id'=>'negative_boundary','input'=>[-100000,-100000],'expected'=>-200000],['id'=>'mixed_signs','input'=>[-7,12],'expected'=>5]]);
                return $task;
            });
        }
        $output->writeln('Coding demo task: '.$task->id.'; card: '.$task->card->id.'. No runner execution.');
        return Command::SUCCESS;
    }
}
