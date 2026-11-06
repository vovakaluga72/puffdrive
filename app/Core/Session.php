<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Работа с сессией: безопасные параметры cookie, flash-сообщения,
 * сохранение введённых данных формы ("old input").
 */
final class Session
{
    private const FLASH_NEW = '_flash_new';
    private const FLASH_OLD = '_flash_old';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_maxlifetime', '86400');

        session_name((string) config('session.name', 'PUFFSESSID'));
        session_set_cookie_params([
            'lifetime' => (int) config('session.lifetime', 0),
            'path'     => base_path() === '' ? '/' : base_path() . '/',
            'secure'   => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();

        // Flash-данные живут ровно один следующий запрос
        $_SESSION[self::FLASH_OLD] = $_SESSION[self::FLASH_NEW] ?? [];
        $_SESSION[self::FLASH_NEW] = [];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION[$key] ?? $default;
        unset($_SESSION[$key]);
        return $value;
    }

    /** Сообщение на следующий запрос (после редиректа). */
    public static function flash(string $key, mixed $value): void
    {
        $_SESSION[self::FLASH_NEW][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        return $_SESSION[self::FLASH_OLD][$key] ?? $default;
    }

    /** Сохраняет введённые данные формы, кроме паролей и служебных полей. */
    public static function flashInput(array $input): void
    {
        $safe = array_filter(
            $input,
            static fn ($value, $key) => !str_contains((string) $key, 'password') && $key !== '_csrf',
            ARRAY_FILTER_USE_BOTH
        );
        self::flash('_old_input', $safe);
    }

    public static function old(string $key, mixed $default = ''): mixed
    {
        return $_SESSION[self::FLASH_OLD]['_old_input'][$key] ?? $default;
    }

    /** Новый ID сессии — обязательно после входа (защита от фиксации сессии). */
    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }
}
