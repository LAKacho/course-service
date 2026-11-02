<?php
namespace App\Learning\Infrastructure\Console;

use App\Learning\Application\Service\ImportService;
use App\Service\Import\ImportValidationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\{InputArgument, InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:learning', description: 'Проверка и атомарный импорт учебной программы из JSON')]
class ImportLearningCommand extends Command
{
    public function __construct(private ImportService $importer, private LoggerInterface $logger) { parent::__construct(); }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'JSON-файл внутри проекта')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Проверить и показать план без записи')
            ->addOption('path-id', null, InputOption::VALUE_REQUIRED, 'Явно привязать корень импорта к существующему направлению');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $pathId = $input->getOption('path-id');
        if ($pathId !== null && (!is_string($pathId) || !ctype_digit($pathId) || (int) $pathId < 1 || strlen($pathId) > 10 || (int) $pathId > 2147483647)) {
            $io->error('--path-id должен быть положительным целым ID направления.');
            return Command::INVALID;
        }
        try {
            $report = $this->importer->import($input->getArgument('file'), $input->getOption('dry-run'), $pathId === null ? null : (int) $pathId);
        } catch (ImportValidationException $exception) {
            $io->error('Импорт отклонён. Данные не записаны.');
            foreach ($exception->errors as $error) { $io->writeln(' • '.OutputFormatter::escape($error)); }
            return Command::INVALID;
        } catch (\Throwable $exception) {
            $this->logger->error('Импорт учебной программы отменён: '.$exception->getMessage(), ['exception' => $exception]);
            $io->error('Импорт отменён; транзакция откачена. Проверьте схему БД, подключение и var/log/dev.log.');
            return Command::FAILURE;
        }
        $rows = [];
        foreach ($report['counts'] as $kind => $count) { $rows[] = [$kind, $count['created'], $count['updated'], $count['unchanged']]; }
        $io->table(['Тип', 'Создать', 'Обновить', 'Без изменений'], $rows);
        foreach ($report['details'] as $detail) {
            $io->writeln(sprintf('%s %s: %s%s', $detail['kind'], $detail['externalId'], $detail['action'], $detail['changes'] ? ' ('.implode(', ', $detail['changes']).')' : ''));
        }
        $io->success($report['dryRun'] ? 'Dry-run: проверка завершена, запись не выполнялась.' : 'Импорт успешно сохранён одной транзакцией.');
        return Command::SUCCESS;
    }
}
