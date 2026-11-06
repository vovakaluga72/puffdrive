<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Ответы, завершающие обработку запроса: редирект и JSON.
 */
final class Response
{
    public static function redirect(string $to, int $status = 302): never
    {
        // Разрешаем только адреса нашего сайта — защита от open redirect
        if (!str_starts_with($to, '/') && !str_starts_with($to, absolute_url('/'))) {
            $to = url('/');
        }
        if (str_starts_with($to, '//')) {
            $to = url('/');
        }

        header('Location: ' . $to, true, $status);
        exit;
    }

    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    /** Возврат на предыдущую страницу (только в пределах сайта). */
    public static function back(string $fallback = '/'): never
    {
        // Берём из Referer только путь и параметры: домен за прокси/туннелем может отличаться
        // от HTTP_HOST, а без домена увести пользователя на чужой сайт невозможно.
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $path    = (string) parse_url($referer, PHP_URL_PATH);

        if ($path !== '' && str_starts_with($path, '/') && !str_starts_with($path, '//')) {
            $query = parse_url($referer, PHP_URL_QUERY);
            self::redirect($path . ($query ? '?' . $query : ''));
        }
        self::redirect(url($fallback));
    }
}
