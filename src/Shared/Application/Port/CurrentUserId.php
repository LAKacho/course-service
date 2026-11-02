<?php
declare(strict_types=1);
namespace App\Shared\Application\Port;

/** Provides the authenticated actor identifier without exposing session or HTTP details. */
interface CurrentUserId { public function id(): string; }
