<?php
namespace App\Learning\Infrastructure\Filesystem;

use App\Learning\Application\Import\ImportValidationException;
use App\Learning\Application\Port\ProgramSource;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ProjectProgramReader implements ProgramSource
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private string $projectDir) {}

    public function read(string $filename): mixed
    {
        $root = realpath($this->projectDir);
        $file = realpath($filename);
        if ($file === false || !is_file($file) || !is_readable($file)) {
            throw new ImportValidationException(['Файл JSON не найден или недоступен для чтения.']);
        }
        $prefix = $root.DIRECTORY_SEPARATOR;
        $inside = PHP_OS_FAMILY === 'Windows'
            ? strncasecmp($file, $prefix, strlen($prefix)) === 0
            : str_starts_with($file, $prefix);
        if (!$inside) {
            throw new ImportValidationException(['Разрешены только файлы внутри текущего проекта.']);
        }
        if (filesize($file) > 2 * 1024 * 1024) {
            throw new ImportValidationException(['Размер JSON превышает 2 MiB. Разделите программу на небольшие файлы.']);
        }
        $json = file_get_contents($file);
        if ($json === false) {
            throw new ImportValidationException(['Не удалось прочитать JSON.']);
        }
        try {
            return json_decode($json, false, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new ImportValidationException(['Некорректный JSON: '.$exception->getMessage()]);
        }
    }
}
