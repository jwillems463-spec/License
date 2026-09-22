<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Loads configuration from config.php (preferred) or, if absent, from a .env file.
 */
final class Config
{
    private static array $items = [];

    public static function load(string $root): void
    {
        $php = $root . '/config.php';
        $env = $root . '/.env';

        if (is_file($php)) {
            $items = require $php;
            self::$items = is_array($items) ? $items : [];
            return;
        }

        if (is_file($env)) {
            self::$items = self::fromEnv(self::parseEnv($env));
            return;
        }

        self::$items = [];
    }

    public static function isConfigured(): bool
    {
        return !empty(self::$items['db']['name']);
    }

    /** Dot-notation lookup, e.g. Config::get('db.host'). */
    public static function get(string $key, $default = null)
    {
        $value = self::$items;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    private static function parseEnv(string $file): array
    {
        $vars = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $v = trim($v);
            if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) {
                $v = substr($v, 1, -1);
            }
            $vars[trim($k)] = $v;
        }
        return $vars;
    }

    private static function fromEnv(array $env): array
    {
        $bool = static fn($v) => in_array(strtolower((string) $v), ['1', 'true', 'yes', 'on'], true);
        return [
            'app' => [
                'name'      => $env['APP_NAME'] ?? 'EV Catalog',
                'url'       => $env['APP_URL'] ?? '',
                'base_path' => $env['APP_BASE_PATH'] ?? '',
                'debug'     => $bool($env['APP_DEBUG'] ?? 'false'),
                'timezone'  => $env['APP_TIMEZONE'] ?? 'UTC',
            ],
            'db' => [
                'host'     => $env['DB_HOST'] ?? 'localhost',
                'port'     => (int) ($env['DB_PORT'] ?? 3306),
                'name'     => $env['DB_DATABASE'] ?? '',
                'user'     => $env['DB_USERNAME'] ?? '',
                'pass'     => $env['DB_PASSWORD'] ?? '',
                'charset'  => 'utf8mb4',
            ],
            'session' => [
                'name'     => $env['SESSION_NAME'] ?? 'evcatalog_session',
                'lifetime' => (int) ($env['SESSION_LIFETIME'] ?? 7200),
            ],
            'uploads' => [
                'max_bytes' => (int) ($env['UPLOAD_MAX_BYTES'] ?? 5242880),
            ],
        ];
    }
}
