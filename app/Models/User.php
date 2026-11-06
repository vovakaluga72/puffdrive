<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Пользователи (покупатели и администраторы).
 */
final class User
{
    /** Требования к паролю — одни и те же при регистрации, смене и восстановлении. */
    public const PASSWORD_RULES = ['required', 'min:8', 'max:72', 'regex:/^(?=.*\p{L})(?=.*\d).+$/u', 'confirmed'];

    public const PASSWORD_MESSAGES = [
        'password.regex' => 'Пароль должен содержать хотя бы одну букву и одну цифру',
    ];

    /** Подписи полей для сообщений об ошибках. */
    public const LABELS = [
        'name'             => 'Имя',
        'email'            => 'Email',
        'phone'            => 'Телефон',
        'password'         => 'Пароль',
        'current_password' => 'Текущий пароль',
        'consent'          => 'Согласие на обработку персональных данных',
    ];

    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function findByEmail(string $email): ?array
    {
        return Database::fetch('SELECT * FROM users WHERE email = ?', [mb_strtolower(trim($email))]);
    }

    /** Регистрация нового покупателя. Пароль хэшируется здесь и только здесь. */
    public static function create(string $name, string $email, ?string $phone, string $password): int
    {
        return Database::insert('users', [
            'name'          => $name,
            'email'         => mb_strtolower(trim($email)),
            'phone'         => $phone,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role'          => 'customer',
        ]);
    }

    public static function updateProfile(int $id, string $name, string $email, ?string $phone): void
    {
        Database::update('users', [
            'name'  => $name,
            'email' => mb_strtolower(trim($email)),
            'phone' => $phone,
        ], 'id = :id', ['id' => $id]);
    }

    public static function updatePassword(int $id, string $password): void
    {
        Database::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = :id', ['id' => $id]);
    }

    public static function verifyPassword(int $id, string $password): bool
    {
        $hash = Database::fetchColumn('SELECT password_hash FROM users WHERE id = ?', [$id]);
        return is_string($hash) && password_verify($password, $hash);
    }

    /** Счётчики для личного кабинета. */
    public static function stats(int $id): array
    {
        return Database::fetch(
            'SELECT (SELECT COUNT(*) FROM orders WHERE user_id = :u1) AS orders_count,
                    (SELECT COALESCE(SUM(total), 0) FROM orders WHERE user_id = :u2 AND status <> \'cancelled\') AS orders_total,
                    (SELECT COUNT(*) FROM reviews WHERE user_id = :u3) AS reviews_count,
                    (SELECT COUNT(*) FROM user_addresses WHERE user_id = :u4) AS addresses_count',
            ['u1' => $id, 'u2' => $id, 'u3' => $id, 'u4' => $id]
        ) ?? [];
    }
}
