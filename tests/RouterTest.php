<?php
namespace App\Tests;

use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    public function testRuntimeCanReloadTheBuiltInServerRouter(): void
    {
        $root = dirname(__DIR__);
        // Reproduce cli-server selecting router.php as SCRIPT_FILENAME.
        // An unknown API route exercises Runtime boot without reading application data.
        $code = <<<'PHP'
$_SERVER['SCRIPT_FILENAME'] = getcwd().'/public/router.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_URI'] = '/api/router-regression-check';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
require getcwd().'/public/router.php';
PHP;
        $environment = array_merge(getenv(), [
            'APP_ENV' => 'test', 'APP_DEBUG' => '0',
            'APP_SECRET' => 'router-test-only', 'DATABASE_URL' => 'sqlite:///:memory:',
        ]);
        $process = proc_open([PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $environment);
        self::assertIsResource($process);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $stderr);
        $response = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(404, $response['error']['status']);
    }
}
