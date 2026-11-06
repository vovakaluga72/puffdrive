<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Models\User;

/**
 * Восстановление пароля по ссылке из письма.
 *
 * Схема:
 *  1. Генерируем случайный токен (32 байта), в письмо кладём сам токен,
 *     а в БД — только его SHA-256. Утечка таблицы не даёт доступа к аккаунтам.
 *  2. Ссылка действует ограниченное время и одноразово.
 *  3. Ответ пользователю всегда одинаковый — нельзя узнать, зарегистрирован ли email.
 */
final class PasswordResetService
{
    /** Не больше стольких запросов на один аккаунт за окно ограничения. */
    private const MAX_REQUESTS = 3;
    private const WINDOW_MINUTES = 15;

    public static function request(string $email): void
    {
        $user = User::findByEmail($email);
        if ($user === null || (int) $user['is_blocked'] === 1) {
            return; // молча: не раскрываем, есть ли такой пользователь
        }

        $recent = (int) Database::fetchColumn(
            'SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND created_at >= ?',
            [(int) $user['id'], date('Y-m-d H:i:s', time() - self::WINDOW_MINUTES * 60)]
        );
        if ($recent >= self::MAX_REQUESTS) {
            Logger::warning('Превышен лимит запросов восстановления пароля', ['user_id' => $user['id']]);
            return;
        }

        $token   = bin2hex(random_bytes(32));
        $ttl     = (int) config('security.password_reset_ttl', 60);
        $expires = date('Y-m-d H:i:s', time() + $ttl * 60);

        Database::transaction(static function () use ($user, $token, $expires): void {
            // Старые неиспользованные ссылки становятся недействительными
            Database::query('DELETE FROM password_resets WHERE user_id = ? AND used_at IS NULL', [(int) $user['id']]);
            Database::insert('password_resets', [
                'user_id'    => (int) $user['id'],
                'token_hash' => hash('sha256', $token),
                'expires_at' => $expires,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        });

        $link = absolute_url('/password/reset/' . $token);
        $sent = Mailer::send($user['email'], 'Восстановление пароля — Puff Drive', 'password-reset', [
            'name' => $user['name'],
            'link' => $link,
            'ttl'  => $ttl,
        ]);

        // Локально почта обычно не настроена — в режиме отладки ссылка пишется в лог
        if (config('app.debug')) {
            Logger::info('Ссылка восстановления пароля (debug)', [
                'email' => $user['email'],
                'link'  => $link,
                'mail'  => $sent ? 'отправлено' : 'НЕ отправлено',
            ]);
        }
    }

    /** Действующая запись для токена из ссылки или null. */
    public static function findValid(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        return Database::fetch(
            'SELECT pr.id, pr.user_id, u.email, u.name
               FROM password_resets pr
               JOIN users u ON u.id = pr.user_id AND u.is_blocked = 0
              WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW()',
            [hash('sha256', $token)]
        );
    }

    /** Установка нового пароля. Возвращает пользователя или null, если ссылка недействительна. */
    public static function reset(string $token, string $password): ?array
    {
        $reset = self::findValid($token);
        if ($reset === null) {
            return null;
        }

        Database::transaction(static function () use ($reset, $password): void {
            User::updatePassword((int) $reset['user_id'], $password);
            Database::update('password_resets', ['used_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $reset['id']]);
            Database::query('DELETE FROM password_resets WHERE user_id = ? AND used_at IS NULL', [(int) $reset['user_id']]);
        });
        Auth::clearAttempts($reset['email']);

        return User::find((int) $reset['user_id']);
    }
}
