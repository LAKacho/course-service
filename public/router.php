<?php
// Router for PHP's built-in server; keep all non-public files outside the document root.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$file = realpath(__DIR__.$path);
if ($file !== false && str_starts_with($file, __DIR__.DIRECTORY_SEPARATOR) && is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'php') { return false; }
// Symfony Runtime may reload this router via SCRIPT_FILENAME and expects its callable.
return require __DIR__.'/index.php';
