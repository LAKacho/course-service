<?php
// Router for PHP's built-in server; keep all non-public files outside the document root.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$file = realpath(__DIR__.$path);
if ($file !== false && str_starts_with($file, __DIR__.DIRECTORY_SEPARATOR) && is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'php') { return false; }
// Symfony Runtime reloads SCRIPT_FILENAME. On that second include, return the app factory.
if (defined('APP_RUNTIME_ROUTER_RELOAD')) { return require dirname(__DIR__).'/config/runtime.php'; }
define('APP_RUNTIME_ROUTER_RELOAD', true);
require_once dirname(__DIR__).'/vendor/autoload_runtime.php';
return require dirname(__DIR__).'/config/runtime.php';
