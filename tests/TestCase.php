<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class TestCase extends WebTestCase
{
    private static function sqlitePath(): string
    {
        return dirname(__DIR__) . '/var/test.db';
    }

    private static function resetSqlite(): void
    {
        foreach (['/var/test.db', '/var/test.db-journal', '/var/test.db-wal', '/var/test.db-shm'] as $suffix) {
            $path = dirname(__DIR__) . $suffix;
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        self::resetSqlite();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
        self::resetSqlite();
        parent::tearDown();
    }
}
