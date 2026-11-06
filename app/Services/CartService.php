<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use App\Models\Product;

/**
 * Корзина.
 *  - Гость: позиции хранятся в сессии ($_SESSION['cart']), промокод — в $_SESSION['cart_promo'].
 *  - Авторизованный пользователь: таблицы carts / cart_items, промокод — carts.promo_code_id.
 *  - При входе гостевая корзина переносится в БД и объединяется с сохранённой.
 *
 * Позиция корзины = вариант товара + опции (конфигурация аппарата).
 * Один и тот же аппарат с разными наборами ароматов — разные позиции.
 * Цены в корзине всегда актуальные (берутся из каталога при каждом показе);
 * «снимок» цены фиксируется только в момент оформления заказа.
 */
final class CartService
{
    private const SESSION_KEY = 'cart';
    private const PROMO_KEY = 'cart_promo';
    public const MAX_QTY = 99;

    /** Общее количество единиц товара (бейдж в шапке). */
    public static function count(): int
    {
        if (Auth::check()) {
            return (int) Database::fetchColumn(
                'SELECT COALESCE(SUM(ci.quantity), 0)
                   FROM cart_items ci JOIN carts c ON c.id = ci.cart_id
                  WHERE c.user_id = ?',
                [(int) Auth::id()]
            );
        }

        $items = Session::get(self::SESSION_KEY, []);
        return is_array($items)
            ? array_sum(array_map(static fn ($item) => (int) ($item['quantity'] ?? 0), $items))
            : 0;
    }

    /**
     * Добавление в корзину с проверками: товар активен, есть на складе,
     * количество не больше остатка, конфигурация аппарата корректна.
     *
     * @return array{ok: bool, error?: string, count?: int, added?: int, item?: array}
     */
    public static function add(int $variantId, int $quantity, mixed $slots = null): array
    {
        $variant = self::variant($variantId);
        if ($variant === null) {
            return ['ok' => false, 'error' => 'Товар не найден или снят с продажи'];
        }
        if ((int) $variant['stock'] <= 0) {
            return ['ok' => false, 'error' => 'Этого варианта сейчас нет в наличии'];
        }

        $options = null;
        if ((int) $variant['aroma_slots'] > 0) {
            $options = MachineConfig::validate($slots, (int) $variant['aroma_slots']);
            if (is_string($options)) {
                return ['ok' => false, 'error' => $options];
            }
        }

        $quantity = max(1, min(self::MAX_QTY, $quantity));
        $hash     = self::hash($options);
        $current  = self::lineQuantity($variantId, $hash);
        $limit    = min((int) $variant['stock'], self::MAX_QTY);
        $newQty   = min($current + $quantity, $limit);

        if ($newQty <= $current) {
            return ['ok' => false, 'error' => 'В корзине уже максимальное доступное количество: ' . $limit . ' шт.'];
        }

        self::setLine($variantId, $hash, $options, $newQty);

        return [
            'ok'    => true,
            'count' => self::count(),
            'added' => $newQty - $current,
            'item'  => [
                'name'    => $variant['product_name'],
                'variant' => $variant['label'],
                'config'  => $options ? MachineConfig::summary($options) : null,
            ],
        ];
    }

    /**
     * Все позиции корзины с актуальными данными каталога.
     * Позиции, которые больше нельзя купить (товар скрыт, нет на складе),
     * помечаются available = false и не входят в сумму.
     */
    public static function items(): array
    {
        $lines = self::rawLines();
        if (!$lines) {
            return [];
        }

        $ids = array_values(array_unique(array_map(static fn ($l) => (int) $l['variant_id'], $lines)));
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $variants = Database::fetchAll(
            'SELECT v.id, v.label, v.sku, v.stock, v.is_active, v.price AS regular_price,
                    ' . Product::EFFECTIVE_PRICE . ' AS price,
                    p.id AS product_id, p.slug, p.name AS product_name, p.aroma_slots, p.is_active AS product_active,
                    c.type AS category_type, c.is_active AS category_active,
                    (SELECT pi.path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_main DESC, pi.sort_order, pi.id LIMIT 1) AS image,
                    (SELECT a.color FROM product_aromas pa JOIN aromas a ON a.id = pa.aroma_id WHERE pa.product_id = p.id ORDER BY a.sort_order LIMIT 1) AS aroma_color
               FROM product_variants v
               JOIN products p ON p.id = v.product_id
               JOIN categories c ON c.id = p.category_id
              WHERE v.id IN (' . $placeholders . ')',
            $ids
        );
        $byId = array_column($variants, null, 'id');

        $items = [];
        foreach ($lines as $line) {
            $v = $byId[(int) $line['variant_id']] ?? null;
            if ($v === null) {
                continue; // вариант удалён из каталога — позиция просто исчезает
            }

            $price    = (float) $v['price'];
            $regular  = (float) $v['regular_price'];
            $stock    = (int) $v['stock'];
            $qty      = (int) $line['quantity'];
            $isActive = (int) $v['is_active'] === 1 && (int) $v['product_active'] === 1 && (int) $v['category_active'] === 1;
            $options  = $line['options'];

            $items[] = [
                'key'           => (string) $line['key'],
                'variant_id'    => (int) $v['id'],
                'product_id'    => (int) $v['product_id'],
                'slug'          => $v['slug'],
                'name'          => $v['product_name'],
                'label'         => $v['label'],
                'sku'           => $v['sku'],
                'category_type' => $v['category_type'],
                'image'         => $v['image'],
                'aroma_color'   => $v['aroma_color'],
                'price'         => $price,
                'old_price'     => $regular > $price ? $regular : null,
                'quantity'      => $qty,
                'max_qty'       => max(1, min($stock, self::MAX_QTY)),
                'stock'         => $stock,
                'line_total'    => round($price * $qty, 2),
                'line_regular'  => round(max($regular, $price) * $qty, 2),
                'options'       => $options,
                'config'        => $options ? MachineConfig::describe($options) : [],
                'available'     => $isActive && $stock > 0,
                'qty_exceeds'   => $isActive && $stock > 0 && $qty > $stock,
            ];
        }
        return $items;
    }

    /**
     * Итоги корзины: сумма, экономия по акциям, промокод, порог бесплатной доставки.
     */
    public static function summary(?array $items = null): array
    {
        $items ??= self::items();
        $available = array_filter($items, static fn ($i) => $i['available']);

        $subtotal = 0.0;
        $regular  = 0.0;
        $units    = 0;
        foreach ($available as $item) {
            $qty       = min($item['quantity'], $item['stock']);
            $subtotal += $item['price'] * $qty;
            $regular  += max($item['old_price'] ?? $item['price'], $item['price']) * $qty;
            $units    += $qty;
        }
        $subtotal = round($subtotal, 2);

        $promo = self::promo();
        $promoError = null;
        $discount = 0.0;
        if ($promo !== null) {
            $promoError = PromoService::check($promo, $subtotal);
            if ($promoError === null) {
                $discount = PromoService::discount($promo, $subtotal);
            }
        }

        $afterDiscount = round($subtotal - $discount, 2);
        $threshold = DeliveryService::freeThreshold();

        return [
            'items_count'    => count($items),
            'units'          => $units,
            'subtotal'       => $subtotal,
            'savings'        => round(max(0, $regular - $subtotal), 2),
            'promo'          => $promo,
            'promo_error'    => $promoError,
            'discount'       => $discount,
            'total'          => $afterDiscount,
            'free_threshold' => $threshold,
            'to_free'        => $threshold > 0 ? round(max(0, $threshold - $afterDiscount), 2) : 0.0,
            'has_unavailable' => count($available) !== count($items),
        ];
    }

    /** Изменение количества (0 — удаление). Количество ограничивается остатком. */
    public static function update(string $key, int $quantity): array
    {
        $line = self::findLine($key);
        if ($line === null) {
            return ['ok' => false, 'error' => 'Позиция не найдена в корзине'];
        }
        if ($quantity <= 0) {
            self::remove($key);
            return ['ok' => true];
        }

        $variant = self::variant((int) $line['variant_id']);
        $limit = $variant ? min(max(1, (int) $variant['stock']), self::MAX_QTY) : 1;
        $qty = min($quantity, $limit);

        self::setLine((int) $line['variant_id'], (string) $line['hash'], $line['options'], $qty);
        return $qty < $quantity
            ? ['ok' => true, 'notice' => 'Доступно только ' . $limit . ' шт.']
            : ['ok' => true];
    }

    /** Текущее количество позиции (0 — нет такой позиции). */
    public static function quantityOf(string $key): int
    {
        return (int) (self::findLine($key)['quantity'] ?? 0);
    }

    public static function remove(string $key): void
    {
        if (Auth::check()) {
            Database::query(
                'DELETE ci FROM cart_items ci JOIN carts c ON c.id = ci.cart_id WHERE ci.id = ? AND c.user_id = ?',
                [(int) $key, (int) Auth::id()]
            );
            return;
        }
        $items = Session::get(self::SESSION_KEY, []);
        unset($items[$key]);
        Session::set(self::SESSION_KEY, $items);
    }

    /** Очистка после оформления заказа (вместе с промокодом). */
    public static function clear(): void
    {
        if (Auth::check()) {
            Database::query('DELETE FROM carts WHERE user_id = ?', [(int) Auth::id()]);
        }
        Session::forget(self::SESSION_KEY);
        Session::forget(self::PROMO_KEY);
    }

    // ------------------------------------------------------------------
    // Промокод
    // ------------------------------------------------------------------

    /** Применённый промокод (запись из promo_codes) или null. */
    public static function promo(): ?array
    {
        $id = Auth::check()
            ? (int) Database::fetchColumn('SELECT promo_code_id FROM carts WHERE user_id = ?', [(int) Auth::id()])
            : (int) Session::get(self::PROMO_KEY, 0);

        return $id > 0 ? PromoService::find($id) : null;
    }

    /** @return string|null ошибка или null при успехе */
    public static function applyPromo(string $code): ?string
    {
        $promo = PromoService::findByCode($code);
        if ($promo === null) {
            return 'Промокод не найден';
        }
        $error = PromoService::check($promo, self::summary()['subtotal']);
        if ($error !== null) {
            return $error;
        }
        self::storePromo((int) $promo['id']);
        return null;
    }

    public static function removePromo(): void
    {
        self::storePromo(null);
    }

    private static function storePromo(?int $promoId): void
    {
        if (Auth::check()) {
            $cartId = self::cartId((int) Auth::id());
            Database::update('carts', ['promo_code_id' => $promoId], 'id = :id', ['id' => $cartId]);
            return;
        }
        $promoId === null ? Session::forget(self::PROMO_KEY) : Session::set(self::PROMO_KEY, $promoId);
    }

    /** Перенос гостевой корзины и промокода в БД после входа (вызывается из Auth::login). */
    public static function mergeGuestCart(int $userId): void
    {
        $items = Session::pull(self::SESSION_KEY, []);
        $promoId = (int) Session::pull(self::PROMO_KEY, 0);

        foreach (is_array($items) ? $items : [] as $item) {
            $variant = self::variant((int) ($item['variant_id'] ?? 0));
            if ($variant === null || (int) $variant['stock'] <= 0) {
                continue;
            }
            $options = $item['options'] ?? null;
            $hash    = self::hash($options);
            $current = self::lineQuantity((int) $variant['id'], $hash, $userId);
            $qty     = min($current + (int) ($item['quantity'] ?? 1), (int) $variant['stock'], self::MAX_QTY);
            self::setLine((int) $variant['id'], $hash, $options, $qty, $userId);
        }

        if ($promoId > 0) {
            $cartId = self::cartId($userId);
            Database::query('UPDATE carts SET promo_code_id = ? WHERE id = ? AND promo_code_id IS NULL', [$promoId, $cartId]);
        }
    }

    // ------------------------------------------------------------------
    // Хранилище
    // ------------------------------------------------------------------

    /** Позиции в «сыром» виде: key, variant_id, quantity, options, hash. */
    private static function rawLines(): array
    {
        if (Auth::check()) {
            $rows = Database::fetchAll(
                'SELECT ci.id, ci.variant_id, ci.quantity, ci.options, ci.options_hash
                   FROM cart_items ci JOIN carts c ON c.id = ci.cart_id
                  WHERE c.user_id = ?
                  ORDER BY ci.created_at, ci.id',
                [(int) Auth::id()]
            );
            return array_map(static fn ($r) => [
                'key'        => (string) $r['id'],
                'variant_id' => (int) $r['variant_id'],
                'quantity'   => (int) $r['quantity'],
                'options'    => $r['options'] ? json_decode($r['options'], true) : null,
                'hash'       => (string) $r['options_hash'],
            ], $rows);
        }

        $items = Session::get(self::SESSION_KEY, []);
        $lines = [];
        foreach (is_array($items) ? $items : [] as $key => $item) {
            $lines[] = [
                'key'        => (string) $key,
                'variant_id' => (int) $item['variant_id'],
                'quantity'   => (int) $item['quantity'],
                'options'    => $item['options'] ?? null,
                'hash'       => self::hash($item['options'] ?? null),
            ];
        }
        return $lines;
    }

    private static function findLine(string $key): ?array
    {
        foreach (self::rawLines() as $line) {
            if ($line['key'] === $key) {
                return $line;
            }
        }
        return null;
    }

    /** Вариант со всем, что нужно для проверок. */
    private static function variant(int $variantId): ?array
    {
        if ($variantId <= 0) {
            return null;
        }
        return Database::fetch(
            'SELECT v.id, v.label, v.sku, v.stock, ' . Product::EFFECTIVE_PRICE . ' AS price,
                    p.id AS product_id, p.name AS product_name, p.aroma_slots
               FROM product_variants v
               JOIN products p ON p.id = v.product_id AND p.is_active = 1
               JOIN categories c ON c.id = p.category_id AND c.is_active = 1
              WHERE v.id = ? AND v.is_active = 1',
            [$variantId]
        );
    }

    private static function hash(?array $options): string
    {
        return $options ? md5((string) json_encode($options)) : '';
    }

    private static function lineQuantity(int $variantId, string $hash, ?int $userId = null): int
    {
        $userId ??= Auth::id();
        if ($userId) {
            return (int) Database::fetchColumn(
                'SELECT ci.quantity FROM cart_items ci JOIN carts c ON c.id = ci.cart_id
                  WHERE c.user_id = ? AND ci.variant_id = ? AND ci.options_hash = ?',
                [$userId, $variantId, $hash]
            );
        }
        $items = Session::get(self::SESSION_KEY, []);
        return (int) ($items[$variantId . ':' . $hash]['quantity'] ?? 0);
    }

    private static function setLine(int $variantId, string $hash, ?array $options, int $quantity, ?int $userId = null): void
    {
        $userId ??= Auth::id();

        if ($userId) {
            $cartId = self::cartId($userId);
            Database::query(
                'INSERT INTO cart_items (cart_id, variant_id, quantity, options, options_hash)
                 VALUES (:cart, :variant, :qty, :options, :hash)
                 ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)',
                [
                    'cart'    => $cartId,
                    'variant' => $variantId,
                    'qty'     => $quantity,
                    'options' => $options ? json_encode($options) : null,
                    'hash'    => $hash,
                ]
            );
            Database::query('UPDATE carts SET updated_at = NOW() WHERE id = ?', [$cartId]);
            return;
        }

        $items = Session::get(self::SESSION_KEY, []);
        $items = is_array($items) ? $items : [];
        $key = $variantId . ':' . $hash;
        $items[$key] = [
            'variant_id' => $variantId,
            'quantity'   => $quantity,
            'options'    => $options,
            'added_at'   => $items[$key]['added_at'] ?? time(),
        ];
        Session::set(self::SESSION_KEY, $items);
    }

    /** ID корзины пользователя (создаётся при первом обращении). */
    private static function cartId(int $userId): int
    {
        Database::query('INSERT IGNORE INTO carts (user_id) VALUES (?)', [$userId]);
        return (int) Database::fetchColumn('SELECT id FROM carts WHERE user_id = ?', [$userId]);
    }
}
