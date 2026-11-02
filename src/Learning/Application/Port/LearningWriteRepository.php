<?php
declare(strict_types=1);
namespace App\Learning\Application\Port;
interface LearningWriteRepository { public function save(object $item): void; }
