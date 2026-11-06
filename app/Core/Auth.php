<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Аутентификация пользователей.
 *  - пароли проверяются через password_verify;
 *  - после входа ID сессии регенерируется;
 *  - не более N неудачных попыток за M минут (по email и по IP);
 *  - заблокированные пользователи войти не могут, а активная сессия у них сбрасывается.
 */
final class Auth
{
    private const SESSION_KEY = 'auth.user_id';

    private static ?array $user = null;
    private static bool $loaded = false;

    /** @var callable[] обработчики, вызываемые после успешного входа (например, слияние корзины) */
    private static array $loginHandlers = [];

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = Session::get(self::SESSION_KEY);
            if ($id) {
                $user = Database::fetch(
                    'SELECT id, name, email, phone, role, is_blocked, created_at FROM users WHERE id = ?',
                    [(int) $id]
                );
                if ($user === null || (int) $user['is_blocked'] === 1) {
                    // Пользователь удалён или заблокирован — завершаем сессию
                    Session::forget(self::SESSION_KEY);
                    $user = null;
                }
                self::$user = $user;
            }
        }
        return self::$user;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int) $user['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? null) === 'admin';
    }

    public static function onLogin(callable $handler): void
    {
        self::$loginHandlers[] = $handler;
    }

    /**
     * Попытка входа. Возвращает null при успехе или текст ошибки.
     */
    public static function attempt(string $email, string $password, string $ip): ?string
    {
        $email = mb_strtolower(trim($email));

        $wait = self::lockoutMinutes($email, $ip);
        if ($wait > 0) {
            return 'Слишком много неудачных попыток входа. Повторите через ' . $wait . ' ' . plural($wait, ['минуту', 'минуты', 'минут']) . '.';
        }

        $user = Database::fetch('SELECT * FROM users WHERE email = ?', [$email]);

        // password_verify выполняем всегда, чтобы время ответа не выдавало существование email
        $hash  = $user['password_hash'] ?? '$2y$12$Ek0XToJaVYnkYL0xhBTNt.XXiriHJZ1nTpaYKniL9rkcixTJ.xZpO';
        $valid = password_verify($password, $hash) && $user !== null;

        if (!$valid) {
            self::recordAttempt($email, $ip, false);
            return 'Неверный email или пароль';
        }

        if ((int) $user['is_blocked'] === 1) {
            self::recordAttempt($email, $ip, false);
            return 'Аккаунт заблокирован. Свяжитесь с магазином, если считаете это ошибкой.';
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            Database::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = :id', ['id' => $user['id']]);
        }

        self::recordAttempt($email, $ip, true);
        self::login($user);
        return null;
    }

    public static function login(array $user): void
    {
        Session::regenerate();
        Csrf::refresh();
        Session::set(self::SESSION_KEY, (int) $user['id']);
        Database::update('users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $user['id']]);

        self::$loaded = false;
        self::$user = null;

        foreach (self::$loginHandlers as $handler) {
            $handler((int) $user['id']);
        }
    }

    public static function logout(): void
    {
        Session::forget(self::SESSION_KEY);
        Session::forget('cart');
        Session::forget('auth.intended');
        Session::regenerate();
        Csrf::refresh();
        self::$user = null;
        self::$loaded = true;
    }

    /** Перечитать данные пользователя из БД (после изменения профиля). */
    public static function refresh(): void
    {
        self::$loaded = false;
        self::$user = null;
    }

    /** Сброс счётчика неудачных попыток (например, после восстановления пароля). */
    public static function clearAttempts(string $email): void
    {
        Database::query('DELETE FROM login_attempts WHERE email = ? AND is_success = 0', [mb_strtolower(trim($email))]);
    }

    /**
     * Безопасный адрес для возврата после входа: только путь внутри сайта.
     * Защита от open redirect вида ?redirect=//evil.com
     */
    public static function intendedUrl(string $default = '/account'): string
    {
        $intended = (string) Session::pull('auth.intended', '');
        $valid = $intended !== ''
            && str_starts_with($intended, '/')
            && !str_starts_with($intended, '//')
            && !str_contains($intended, '\\')
            && !preg_match('#^/(login|register|password|logout)#', substr($intended, strlen(base_path())));

        return $valid ? $intended : url($default);
    }

    /** Сколько минут осталось до разблокировки входа (0 — можно входить). */
    public static function lockoutMinutes(string $email, string $ip): int
    {
        $max    = (int) config('security.login_max_attempts', 5);
        $window = (int) config('security.login_window_minutes', 15);
        $since  = date('Y-m-d H:i:s', time() - $window * 60);

        $row = Database::fetch(
            'SELECT COUNT(*) AS cnt, MIN(created_at) AS first_at
               FROM login_attempts
              WHERE is_success = 0 AND created_at >= ? AND (email = ? OR ip = ?)',
            [$since, $email, $ip]
        );

        if ((int) ($row['cnt'] ?? 0) < $max) {
            return 0;
        }
        $unlockAt = strtotime((string) $row['first_at']) + $window * 60;
        return max(1, (int) ceil(($unlockAt - time()) / 60));
    }

    private static function recordAttempt(string $email, string $ip, bool $success): void
    {
        Database::insert('login_attempts', [
            'email'      => mb_substr($email, 0, 190),
            'ip'         => $ip,
            'is_success' => $success ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        if ($success) {
            // Успешный вход сбрасывает счётчик неудач для этого email
            Database::query('DELETE FROM login_attempts WHERE email = ? AND is_success = 0', [$email]);
        }

        // Изредка чистим журнал от записей старше суток, чтобы таблица не росла бесконечно
        if (random_int(1, 50) === 1) {
            Database::query('DELETE FROM login_attempts WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
        }
    }
}
