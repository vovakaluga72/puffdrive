<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Отзывы покупателей. Публикуются только после модерации (status = approved).
 */
final class Review
{
    /** Последние одобренные отзывы для главной. */
    public static function latestApproved(int $limit = 6): array
    {
        return Database::fetchAll(
            'SELECT r.id, r.rating, r.text, r.is_verified_purchase, r.created_at,
                    u.name AS author, p.name AS product_name, p.slug AS product_slug
               FROM reviews r
               JOIN users u ON u.id = r.user_id
               JOIN products p ON p.id = r.product_id AND p.is_active = 1
              WHERE r.status = \'approved\'
              ORDER BY r.created_at DESC
              LIMIT ' . max(1, $limit)
        );
    }

    public const STATUSES = [
        'pending'  => 'На модерации',
        'approved' => 'Опубликован',
        'rejected' => 'Отклонён',
    ];

    /** Опубликованные отзывы товара. */
    public static function forProduct(int $productId): array
    {
        return Database::fetchAll(
            'SELECT r.id, r.rating, r.text, r.is_verified_purchase, r.created_at, u.name AS author
               FROM reviews r JOIN users u ON u.id = r.user_id
              WHERE r.product_id = ? AND r.status = \'approved\'
              ORDER BY r.created_at DESC',
            [$productId]
        );
    }

    /** Распределение оценок: [5 => 10, 4 => 3, …] для гистограммы. */
    public static function distribution(int $productId): array
    {
        $rows = Database::fetchAll(
            'SELECT rating, COUNT(*) AS cnt FROM reviews WHERE product_id = ? AND status = \'approved\' GROUP BY rating',
            [$productId]
        );
        $result = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($rows as $row) {
            $result[(int) $row['rating']] = (int) $row['cnt'];
        }
        return $result;
    }

    /** Отзывы пользователя для личного кабинета (все статусы). */
    public static function forUser(int $userId): array
    {
        return Database::fetchAll(
            'SELECT r.id, r.rating, r.text, r.status, r.is_verified_purchase, r.created_at,
                    p.name AS product_name, p.slug AS product_slug, p.is_active AS product_active
               FROM reviews r
               JOIN products p ON p.id = r.product_id
              WHERE r.user_id = ?
              ORDER BY r.created_at DESC',
            [$userId]
        );
    }

    /** Пересчёт среднего рейтинга товара после модерации. */
    public static function recalculate(int $productId): void
    {
        Database::query(
            'UPDATE products p
                SET p.rating_avg = COALESCE((SELECT ROUND(AVG(r.rating), 2) FROM reviews r WHERE r.product_id = p.id AND r.status = \'approved\'), 0),
                    p.rating_count = (SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.id AND r.status = \'approved\')
              WHERE p.id = ?',
            [$productId]
        );
    }
}
