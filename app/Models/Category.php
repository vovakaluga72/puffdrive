<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Категории каталога.
 */
final class Category
{
    /** Активные категории с количеством активных товаров и минимальной ценой. */
    public static function allWithCounts(): array
    {
        return Database::fetchAll(
            'SELECT c.id, c.type, c.slug, c.name, c.description, c.image,
                    COUNT(DISTINCT p.id) AS products_count,
                    MIN(' . Product::EFFECTIVE_PRICE . ') AS min_price
               FROM categories c
               LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1
               LEFT JOIN product_variants v ON v.product_id = p.id AND v.is_active = 1
              WHERE c.is_active = 1
              GROUP BY c.id
              ORDER BY c.sort_order, c.name'
        );
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::fetch('SELECT * FROM categories WHERE slug = ? AND is_active = 1', [$slug]);
    }

    /** Подпись типа для интерфейса. */
    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'liquid'  => 'Жидкость',
            'machine' => 'Аппарат',
            'part'    => 'Комплектующее',
            default   => '',
        };
    }
}
