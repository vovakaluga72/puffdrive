<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Промокоды: процент или фиксированная сумма, минимальная сумма заказа,
 * период действия, лимит использований, активность.
 */
final class PromoService
{
    public static function findByCode(string $code): ?array
    {
        $code = mb_strtoupper(trim($code));
        if ($code === '' || mb_strlen($code) > 40) {
            return null;
        }
        return Database::fetch('SELECT * FROM promo_codes WHERE UPPER(code) = ?', [$code]);
    }

    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM promo_codes WHERE id = ?', [$id]);
    }

    /**
     * Можно ли применить промокод к сумме заказа.
     * @return string|null текст ошибки или null, если всё в порядке
     */
    public static function check(array $promo, float $subtotal): ?string
    {
        $now = time();

        if ((int) $promo['is_active'] !== 1) {
            return 'Промокод не действует';
        }
        if ($promo['starts_at'] !== null && strtotime($promo['starts_at']) > $now) {
            return 'Промокод начнёт действовать ' . format_date($promo['starts_at']);
        }
        if ($promo['ends_at'] !== null && strtotime($promo['ends_at']) < $now) {
            return 'Срок действия промокода истёк';
        }
        if ($promo['usage_limit'] !== null && (int) $promo['used_count'] >= (int) $promo['usage_limit']) {
            return 'Лимит использований промокода исчерпан';
        }
        if ($subtotal < (float) $promo['min_order_amount']) {
            return 'Промокод действует при заказе от ' . money((float) $promo['min_order_amount']);
        }
        return null;
    }

    /** Размер скидки (не больше суммы товаров). */
    public static function discount(array $promo, float $subtotal): float
    {
        $value = (float) $promo['value'];
        $discount = $promo['type'] === 'percent'
            ? round($subtotal * min(100, $value) / 100, 2)
            : $value;
        return round(min($discount, $subtotal), 2);
    }

    /** Подпись: «−10%» или «−5.00 BYN». */
    public static function label(array $promo): string
    {
        return $promo['type'] === 'percent'
            ? '−' . rtrim(rtrim(number_format((float) $promo['value'], 2, '.', ''), '0'), '.') . '%'
            : '−' . money((float) $promo['value']);
    }

    /**
     * Атомарно засчитывает использование при оформлении заказа.
     * Условие в WHERE защищает от превышения лимита при одновременных заказах.
     */
    public static function consume(int $promoId): bool
    {
        return Database::query(
            'UPDATE promo_codes SET used_count = used_count + 1
              WHERE id = ? AND is_active = 1 AND (usage_limit IS NULL OR used_count < usage_limit)',
            [$promoId]
        )->rowCount() === 1;
    }
}
