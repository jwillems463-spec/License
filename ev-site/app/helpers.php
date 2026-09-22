<?php
declare(strict_types=1);

use App\Core\Config;

/** HTML-escape a value for output in templates. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Base path of the app (e.g. "" or "/ev" when installed in a subfolder). */
function base_path(): string
{
    static $base = null;
    if ($base === null) {
        $configured = Config::get('app.base_path');
        if ($configured !== null && $configured !== '') {
            $base = '/' . trim((string) $configured, '/');
        } else {
            $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
            $base = rtrim($dir, '/');
        }
        if ($base === '/') {
            $base = '';
        }
    }
    return $base;
}

/** Build an app-relative URL. */
function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

/** Versioned asset URL (cache-busts on file change). */
function asset(string $path): string
{
    $file = APP_ROOT . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? (string) filemtime($file) : '1';
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/** Turn arbitrary text into a URL slug. */
function slugify(string $text): string
{
    $text = strtolower(trim($text));
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
    }
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text !== '' ? substr($text, 0, 180) : 'item';
}
