<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Товары. Цена товара в списках — цена самого дешёвого активного варианта
 * с учётом действующей акции.
 */
final class Product
{
    /** Условие «акция варианта действует сейчас» (алиас таблицы вариантов — v). */
    public const SALE_ACTIVE = '(v.sale_price IS NOT NULL AND v.sale_price < v.price
        AND (v.sale_starts_at IS NULL OR v.sale_starts_at <= NOW())
        AND (v.sale_ends_at IS NULL OR v.sale_ends_at >= NOW()))';

    /** Текущая цена варианта с учётом акции. */
    public const EFFECTIVE_PRICE = 'CASE WHEN ' . self::SALE_ACTIVE . ' THEN v.sale_price ELSE v.price END';

    /**
     * Базовый SELECT для карточек товаров: категория, главное фото, аромат,
     * минимальная цена и её «старая» цена, остаток, максимальная скидка.
     * Дополняется условиями WHERE / ORDER BY / LIMIT в конкретных методах.
     */
    public static function cardSelect(): string
    {
        $eff  = self::EFFECTIVE_PRICE;
        $sale = self::SALE_ACTIVE;

        return "SELECT p.id, p.slug, p.name, p.short_description, p.aroma_slots,
                       p.rating_avg, p.rating_count, p.sales_count, p.is_featured, p.created_at,
                       c.slug AS category_slug, c.name AS category_name, c.type AS category_type,
                       (SELECT pi.path FROM product_images pi
                         WHERE pi.product_id = p.id
                         ORDER BY pi.is_main DESC, pi.sort_order, pi.id LIMIT 1) AS image,
                       (SELECT a.name FROM product_aromas pa JOIN aromas a ON a.id = pa.aroma_id
                         WHERE pa.product_id = p.id ORDER BY a.sort_order LIMIT 1) AS aroma_name,
                       (SELECT a.color FROM product_aromas pa JOIN aromas a ON a.id = pa.aroma_id
                         WHERE pa.product_id = p.id ORDER BY a.sort_order LIMIT 1) AS aroma_color,
                       cheapest.id AS variant_id,
                       cheapest.eff_price AS price,
                       CASE WHEN cheapest.eff_price < cheapest.price THEN cheapest.price END AS old_price,
                       agg.variants_count, agg.total_stock, agg.max_discount
                  FROM products p
                  JOIN categories c ON c.id = p.category_id AND c.is_active = 1
                  JOIN (
                        SELECT ranked.* FROM (
                            SELECT v.id, v.product_id, v.price, {$eff} AS eff_price,
                                   ROW_NUMBER() OVER (PARTITION BY v.product_id ORDER BY {$eff}, v.sort_order, v.id) AS rn
                              FROM product_variants v
                             WHERE v.is_active = 1
                        ) ranked WHERE ranked.rn = 1
                  ) cheapest ON cheapest.product_id = p.id
                  JOIN (
                        SELECT v.product_id,
                               COUNT(*) AS variants_count,
                               SUM(v.stock) AS total_stock,
                               MAX(CASE WHEN {$sale} THEN ROUND((v.price - v.sale_price) / v.price * 100) ELSE 0 END) AS max_discount
                          FROM product_variants v
                         WHERE v.is_active = 1
                         GROUP BY v.product_id
                  ) agg ON agg.product_id = p.id
                 WHERE p.is_active = 1";
    }

    /** Популярные: сначала отмеченные «в избранном», затем по продажам. */
    public static function popular(int $limit = 8): array
    {
        return self::cast(Database::fetchAll(
            self::cardSelect() . ' ORDER BY p.is_featured DESC, p.sales_count DESC, p.id LIMIT ' . max(1, $limit)
        ));
    }

    /** Товары с действующей скидкой хотя бы на один вариант. */
    public static function onSale(int $limit = 4): array
    {
        return self::cast(Database::fetchAll(
            self::cardSelect() . ' AND agg.max_discount > 0 ORDER BY agg.max_discount DESC, p.sales_count DESC LIMIT ' . max(1, $limit)
        ));
    }

    public static function findBySlug(string $slug): ?array
    {
        $rows = self::cast(Database::fetchAll(self::cardSelect() . ' AND p.slug = :slug LIMIT 1', ['slug' => $slug]));
        return $rows[0] ?? null;
    }

    /** Полные данные для страницы товара (только активный товар активной категории). */
    public static function findActive(string $slug): ?array
    {
        $product = Database::fetch(
            'SELECT p.*, c.slug AS category_slug, c.name AS category_name, c.type AS category_type
               FROM products p
               JOIN categories c ON c.id = p.category_id AND c.is_active = 1
              WHERE p.slug = ? AND p.is_active = 1',
            [$slug]
        );
        if ($product === null) {
            return null;
        }

        $product['rating_avg']   = (float) $product['rating_avg'];
        $product['rating_count'] = (int) $product['rating_count'];
        $product['aroma_slots']  = (int) $product['aroma_slots'];
        $specs = json_decode((string) ($product['specs'] ?? ''), true);
        $product['specs'] = is_array($specs) ? $specs : [];
        return $product;
    }

    /** Активные варианты с текущей ценой и «старой» ценой при действующей акции. */
    public static function variants(int $productId): array
    {
        $rows = Database::fetchAll(
            'SELECT v.id, v.sku, v.label, v.volume_ml, v.stock, v.price AS regular_price,
                    ' . self::EFFECTIVE_PRICE . ' AS price,
                    CASE WHEN ' . self::SALE_ACTIVE . ' THEN v.sale_ends_at END AS sale_ends_at
               FROM product_variants v
              WHERE v.product_id = ? AND v.is_active = 1
              ORDER BY v.sort_order, v.id',
            [$productId]
        );
        foreach ($rows as &$row) {
            $row['price']     = (float) $row['price'];
            $row['old_price'] = (float) $row['regular_price'] > $row['price'] ? (float) $row['regular_price'] : null;
            $row['stock']     = (int) $row['stock'];
        }
        unset($row);
        return $rows;
    }

    public static function images(int $productId): array
    {
        return Database::fetchAll(
            'SELECT id, path, alt FROM product_images WHERE product_id = ? ORDER BY is_main DESC, sort_order, id',
            [$productId]
        );
    }

    public static function aromas(int $productId): array
    {
        return Database::fetchAll(
            'SELECT a.id, a.slug, a.name, a.color, a.description
               FROM product_aromas pa JOIN aromas a ON a.id = pa.aroma_id
              WHERE pa.product_id = ?
              ORDER BY a.sort_order',
            [$productId]
        );
    }

    /** Похожие: сначала с тем же ароматом, затем популярные из той же категории. */
    public static function similar(array $product, int $limit = 4): array
    {
        return self::cast(Database::fetchAll(
            self::cardSelect() . '
               AND p.category_id = :cat AND p.id <> :id
             ORDER BY (SELECT COUNT(*) FROM product_aromas x
                        WHERE x.product_id = p.id
                          AND x.aroma_id IN (SELECT y.aroma_id FROM product_aromas y WHERE y.product_id = :id2)) DESC,
                      p.sales_count DESC
             LIMIT ' . max(1, $limit),
            ['cat' => (int) $product['category_id'], 'id' => (int) $product['id'], 'id2' => (int) $product['id']]
        ));
    }

    /** Счётчик просмотров (используется в статистике админки). */
    public static function incrementViews(int $productId): void
    {
        Database::query('UPDATE products SET views_count = views_count + 1 WHERE id = ?', [$productId]);
    }

    /** Краткий список для живого поиска в шапке. */
    public static function suggest(string $q, int $limit = 6): array
    {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        return self::cast(Database::fetchAll(
            self::cardSelect() . '
               AND (p.name LIKE :q1 OR p.short_description LIKE :q2
                    OR EXISTS (SELECT 1 FROM product_aromas pa JOIN aromas a ON a.id = pa.aroma_id
                                WHERE pa.product_id = p.id AND a.name LIKE :q3))
             ORDER BY CASE WHEN p.name LIKE :q4 THEN 0 ELSE 1 END, p.sales_count DESC
             LIMIT ' . max(1, $limit),
            ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like]
        ));
    }

    /** Приведение числовых полей к правильным типам. */
    public static function cast(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['price']          = (float) $row['price'];
            $row['old_price']      = $row['old_price'] !== null ? (float) $row['old_price'] : null;
            $row['rating_avg']     = (float) $row['rating_avg'];
            $row['rating_count']   = (int) $row['rating_count'];
            $row['variants_count'] = (int) $row['variants_count'];
            $row['total_stock']    = (int) $row['total_stock'];
            $row['max_discount']   = (int) $row['max_discount'];
        }
        unset($row);
        return $rows;
    }

    public static function url(array $product): string
    {
        return url('/product/' . $product['slug']);
    }
}
