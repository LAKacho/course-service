<?php
declare(strict_types=1);
namespace App\Shared\Infrastructure\Persistence;
/** Database advisory-lock key shared by prerequisite edits and quest transitions. */
final class QuestGraphLock { public const KEY = 843014; }
