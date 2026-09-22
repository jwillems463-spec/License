<?php
declare(strict_types=1);

/**
 * EV Catalog — front controller.
 * Every request (pages + /api/*) is routed through this file by .htaccess.
 */

use App\Controllers\PageController;
use App\Core\Config;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;

if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    exit('EV Catalog requires PHP 8.0 or newer. Select a newer PHP version in cPanel > MultiPHP Manager.');
}

require __DIR__ . '/app/bootstrap.php';

Response::securityHeaders();

$request = new Request();

if (!Config::isConfigured()) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Setup required</title>'
        . '<body style="font-family:system-ui;max-width:640px;margin:4rem auto;padding:0 1rem;line-height:1.6">'
        . '<h1>Setup required</h1><p>Copy <code>config.sample.php</code> to <code>config.php</code> '
        . '(or <code>.env.example</code> to <code>.env</code>) and enter your MySQL database details. '
        . 'See <code>docs/DEPLOYMENT.md</code>.</p></body>';
    exit;
}

$router = new Router();
(require __DIR__ . '/app/routes.php')($router);

try {
    $router->dispatch($request);
} catch (HttpException $e) {
    if ($request->isApi()) {
        Response::error($e->getCode(), $e->getMessage(), $e->fields, $e->errorCode);
    } elseif ($e->getCode() === 404) {
        PageController::notFound();
    } else {
        http_response_code($e->getCode());
        echo e($e->getMessage());
    }
} catch (\PDOException $e) {
    error_log('[ev-catalog] DB error: ' . $e->getMessage());
    $msg = Config::get('app.debug') ? 'Database error: ' . $e->getMessage() : 'A database error occurred.';
    if ($request->isApi()) {
        Response::error(500, $msg);
    } else {
        http_response_code(500);
        echo '<h1>Server error</h1><p>' . e($msg) . '</p>';
    }
} catch (\Throwable $e) {
    error_log('[ev-catalog] ' . $e);
    $msg = Config::get('app.debug') ? get_class($e) . ': ' . $e->getMessage() : 'An unexpected error occurred.';
    if ($request->isApi()) {
        Response::error(500, $msg);
    } else {
        http_response_code(500);
        echo '<h1>Server error</h1><p>' . e($msg) . '</p>';
    }
}
