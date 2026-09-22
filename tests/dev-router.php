<?php
// Router for PHP's built-in dev server (which ignores .htaccess).
// Usage: php -S localhost:8080 -t ev-site tests/dev-router.php
$root = dirname(__DIR__) . '/ev-site';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$file = realpath($root . $path);
$blocked = preg_match('#^/(app|views)(/|$)|^/\.|^/config(\.sample)?\.php$#', $path);
if ($blocked) {
    http_response_code(403);
    exit('Forbidden');
}
if ($path !== '/' && $file && strpos($file, $root) === 0 && is_file($file) && substr($file, -4) !== '.php') {
    return false; // serve static asset as-is
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $root . '/index.php';
