<?php
declare(strict_types=1);
namespace App\Access\Application\Port;
use App\Access\Domain\GrantDefinition;
/** Internal policy-data boundary. No proposed HTTP contract or role mapping. */
interface GrantSource { /** @return list<GrantDefinition> */ public function forUser(string $userId): array; }
