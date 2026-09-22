<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    public string $method;
    public string $path;
    public array $query;
    public array $params = [];
    private ?array $json = null;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->query = $_GET;

        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = base_path();
        if ($base !== '' && strncmp($uri, $base, strlen($base)) === 0) {
            $uri = substr($uri, strlen($base));
        }
        $uri = '/' . trim(rawurldecode($uri), '/');
        if ($uri === '/index.php') {
            $uri = '/';
        }
        $this->path = $uri;
    }

    public function isApi(): bool
    {
        return $this->path === '/api' || strncmp($this->path, '/api/', 5) === 0;
    }

    /** Parsed JSON body (falls back to form POST data). */
    public function body(): array
    {
        if ($this->json === null) {
            $raw = file_get_contents('php://input') ?: '';
            $type = $_SERVER['CONTENT_TYPE'] ?? '';
            if (stripos($type, 'application/json') !== false) {
                if ($raw === '') {
                    $this->json = [];
                } else {
                    $decoded = json_decode($raw, true);
                    if (!is_array($decoded)) {
                        throw new HttpException(400, 'Invalid JSON body.');
                    }
                    $this->json = $decoded;
                }
            } else {
                $this->json = $_POST;
            }
        }
        return $this->json;
    }

    public function input(string $key, $default = null)
    {
        return $this->body()[$key] ?? $default;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? null;
    }

    public function param(string $key): string
    {
        return (string) ($this->params[$key] ?? '');
    }
}
