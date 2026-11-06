<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Хранилище настроек из config/config.php.
 * Доступ через точечную нотацию: Config::get('db.host').
 */
final class Config
{
    private static array $items = [];

    public static function load(string $dir): void
    {
        $file = $dir . '/config.php';

        if (!is_file($file)) {
            // Сайт ещё не настроен — понятное сообщение вместо белого экрана
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
            echo '<h1>Puff Drive: сайт не настроен</h1>'
               . '<p>Скопируйте <code>config/config.example.php</code> в <code>config/config.php</code> '
               . 'и укажите параметры подключения к базе данных.</p>';
            exit;
        }

        $config = require $file;
        self::$items = is_array($config) ? $config : [];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }
}
