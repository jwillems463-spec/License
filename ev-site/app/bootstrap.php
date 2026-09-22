<?php
declare(strict_types=1);

use App\Core\Config;
use App\Core\Session;

define('APP_ROOT', dirname(__DIR__));
define('APP_DIR', __DIR__);

spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'App\\', 4) !== 0) {
        return;
    }
    $file = APP_DIR . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require APP_DIR . '/helpers.php';

Config::load(APP_ROOT);

date_default_timezone_set((string) Config::get('app.timezone', 'UTC'));

if (Config::get('app.debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

Session::start();
