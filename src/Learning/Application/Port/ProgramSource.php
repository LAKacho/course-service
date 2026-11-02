<?php
declare(strict_types=1);
namespace App\Learning\Application\Port;
interface ProgramSource { public function read(string $filename): mixed; }
