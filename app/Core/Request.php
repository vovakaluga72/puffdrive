<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Обёртка над суперглобальными массивами текущего HTTP-запроса.
 */
final class Request
{
    private ?array $json = null;

    private function __construct(
        private array $get,
        private array $post,
        private array $server,
        private array $files,
        private array $cookies,
    ) {
    }

    public static function capture(): self
    {
        return new self($_GET, $_POST, $_SERVER, $_FILES, $_COOKIE);
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    /**
     * Путь запроса относительно корня сайта (учитывает установку в подпапку).
     * Например: "/catalog/zhidkosti".
     */
    public function path(): string
    {
        $uri  = rawurldecode((string) parse_url($this->server['REQUEST_URI'] ?? '/', PHP_URL_PATH));
        $base = base_path();

        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        // Если сайт открыт через корневой .htaccess как /public/... — убираем префикс
        if (str_starts_with($uri, '/public/') || $uri === '/public') {
            $uri = substr($uri, 7);
        }
        if (str_starts_with($uri, '/index.php')) {
            $uri = substr($uri, 10);
        }

        $uri = '/' . trim($uri, '/');
        return $uri;
    }

    /** Исходный путь запроса — нужен, чтобы понять, был ли слэш на конце. */
    public function rawPath(): string
    {
        return (string) parse_url($this->server['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->get[$key] ?? $default;
    }

    public function queryAll(): array
    {
        return $this->get;
    }

    /** Значение из тела запроса (form-data или JSON), затем из query string. */
    public function input(string $key, mixed $default = null): mixed
    {
        $body = $this->body();
        if (array_key_exists($key, $body)) {
            return $body[$key];
        }
        return $this->get[$key] ?? $default;
    }

    /** Строковое значение с обрезкой пробелов. */
    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);
        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key);
        return is_numeric($value) ? (int) $value : $default;
    }

    public function bool(string $key): bool
    {
        return in_array($this->input($key), [1, '1', 'on', 'true', 'yes', true], true);
    }

    public function all(): array
    {
        return array_merge($this->get, $this->body());
    }

    public function only(array $keys): array
    {
        $all = $this->all();
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $all[$key] ?? null;
        }
        return $result;
    }

    public function body(): array
    {
        if ($this->isJson()) {
            if ($this->json === null) {
                $decoded = json_decode((string) file_get_contents('php://input'), true);
                $this->json = is_array($decoded) ? $decoded : [];
            }
            return $this->json;
        }
        return $this->post;
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return isset($this->server[$key]) ? (string) $this->server[$key] : null;
    }

    public function isJson(): bool
    {
        return str_contains(strtolower((string) ($this->server['CONTENT_TYPE'] ?? '')), 'application/json');
    }

    /** Запрос пришёл из fetch() нашего JS. */
    public function isAjax(): bool
    {
        return strtolower((string) $this->header('X-Requested-With')) === 'xmlhttprequest';
    }

    public function wantsJson(): bool
    {
        return $this->isAjax() || str_contains((string) $this->header('Accept'), 'application/json');
    }

    public function ip(): string
    {
        $ip = (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    public function userAgent(): string
    {
        return mb_substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 500);
    }

    public function isSecure(): bool
    {
        return is_https();
    }

    public function referer(): ?string
    {
        return $this->server['HTTP_REFERER'] ?? null;
    }
}
