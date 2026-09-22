<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function json($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    public static function error(int $status, string $message, array $fields = [], ?string $code = null): void
    {
        $err = ['message' => $message];
        if ($code !== null) {
            $err['code'] = $code;
        }
        if ($fields) {
            $err['fields'] = $fields;
        }
        self::json(['error' => $err], $status);
    }

    /** Render a PHP template from /views. */
    public static function view(string $template, array $data = [], int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo View::render($template, $data);
    }

    public static function redirect(string $to): void
    {
        header('Location: ' . $to, true, 302);
    }

    public static function securityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    }
}
