<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Сохранённые адреса доставки пользователя.
 * Все методы принимают user_id — чужой адрес нельзя ни прочитать, ни изменить.
 */
final class Address
{
    public const MAX_PER_USER = 10;

    public static function forUser(int $userId): array
    {
        return Database::fetchAll(
            'SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC',
            [$userId]
        );
    }

    public static function findForUser(int $id, int $userId): ?array
    {
        return Database::fetch('SELECT * FROM user_addresses WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    public static function defaultFor(int $userId): ?array
    {
        return Database::fetch(
            'SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC LIMIT 1',
            [$userId]
        );
    }

    public static function countForUser(int $userId): int
    {
        return (int) Database::fetchColumn('SELECT COUNT(*) FROM user_addresses WHERE user_id = ?', [$userId]);
    }

    /** Первый адрес пользователя автоматически становится основным. */
    public static function create(int $userId, array $data): int
    {
        return Database::transaction(static function () use ($userId, $data): int {
            $isFirst = self::countForUser($userId) === 0;
            $makeDefault = $isFirst || !empty($data['is_default']);
            if ($makeDefault) {
                self::clearDefault($userId);
            }
            return Database::insert('user_addresses', self::columns($data) + [
                'user_id'    => $userId,
                'is_default' => $makeDefault ? 1 : 0,
            ]);
        });
    }

    public static function update(int $id, int $userId, array $data): void
    {
        Database::transaction(static function () use ($id, $userId, $data): void {
            $columns = self::columns($data);
            if (!empty($data['is_default'])) {
                self::clearDefault($userId);
                $columns['is_default'] = 1;
            }
            Database::update('user_addresses', $columns, 'id = :id AND user_id = :uid', ['id' => $id, 'uid' => $userId]);
        });
    }

    public static function setDefault(int $id, int $userId): void
    {
        Database::transaction(static function () use ($id, $userId): void {
            self::clearDefault($userId);
            Database::update('user_addresses', ['is_default' => 1], 'id = :id AND user_id = :uid', ['id' => $id, 'uid' => $userId]);
        });
    }

    /** Удаление; если удалили основной адрес — основным становится самый новый из оставшихся. */
    public static function delete(int $id, int $userId): void
    {
        Database::transaction(static function () use ($id, $userId): void {
            $wasDefault = (int) Database::fetchColumn(
                'SELECT is_default FROM user_addresses WHERE id = ? AND user_id = ?',
                [$id, $userId]
            ) === 1;

            Database::query('DELETE FROM user_addresses WHERE id = ? AND user_id = ?', [$id, $userId]);

            if ($wasDefault) {
                Database::query(
                    'UPDATE user_addresses SET is_default = 1 WHERE user_id = ? ORDER BY id DESC LIMIT 1',
                    [$userId]
                );
            }
        });
    }

    /** Однострочное представление адреса для списков и оформления заказа. */
    public static function format(array $address): string
    {
        $parts = array_filter([
            $address['postcode'] ?? null,
            $address['city'] ?? null,
            $address['address'] ?? null,
        ]);
        return implode(', ', $parts);
    }

    private static function clearDefault(int $userId): void
    {
        Database::query('UPDATE user_addresses SET is_default = 0 WHERE user_id = ?', [$userId]);
    }

    private static function columns(array $data): array
    {
        return [
            'title'     => $data['title'] !== '' ? $data['title'] : 'Адрес',
            'recipient' => $data['recipient'] ?: null,
            'phone'     => $data['phone'] ?: null,
            'city'      => $data['city'],
            'address'   => $data['address'],
            'postcode'  => $data['postcode'] ?: null,
        ];
    }
}
