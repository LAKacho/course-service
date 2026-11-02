<?php
declare(strict_types=1);
namespace App\Assessment\Application\Port;
interface AssessmentBankSource { public function read(): array; }
