<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Заказы. Оформление и детали заказа — этап 4; здесь — то, что нужно личному кабинету.
 */
final class Order
{
    public const STATUSES = [
        'new'       => 'Новый',
        'confirmed' => 'Подтверждён',
        'paid'      => 'Оплачен',
        'shipped'   => 'Отправлен',
        'delivered' => 'Доставлен',
        'cancelled' => 'Отменён',
    ];

    public const PAYMENT_STATUSES = [
        'pending'  => 'Ожидает оплаты',
        'paid'     => 'Оплачен',
        'failed'   => 'Ошибка оплаты',
        'refunded' => 'Возврат',
    ];

    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM orders WHERE id = ?', [$id]);
    }

    public static function findByNumber(string $number): ?array
    {
        return Database::fetch('SELECT * FROM orders WHERE number = ?', [$number]);
    }

    /** Позиции заказа; конфигурация аппарата уже содержит названия ароматов (снимок). */
    public static function items(int $orderId): array
    {
        $rows = Database::fetchAll(
            'SELECT oi.*, p.slug AS product_slug, p.is_active AS product_active
               FROM order_items oi
               LEFT JOIN products p ON p.id = oi.product_id
              WHERE oi.order_id = ?
              ORDER BY oi.id',
            [$orderId]
        );
        foreach ($rows as &$row) {
            $options = $row['options'] ? json_decode($row['options'], true) : null;
            $row['config'] = is_array($options['slots'] ?? null) ? $options['slots'] : [];
        }
        unset($row);
        return $rows;
    }

    public static function history(int $orderId): array
    {
        return Database::fetchAll(
            'SELECT status, comment, created_at FROM order_status_history WHERE order_id = ? ORDER BY created_at, id',
            [$orderId]
        );
    }

    public static function lastPayment(int $orderId): ?array
    {
        return Database::fetch('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1', [$orderId]);
    }

    /**
     * Доступ к странице заказа: владелец-пользователь или ссылка с секретным токеном
     * (гость получает её в письме и сразу после оформления).
     */
    public static function canView(array $order, ?string $token, ?int $userId): bool
    {
        if ($userId !== null && (int) $order['user_id'] === $userId) {
            return true;
        }
        return is_string($token) && $token !== '' && hash_equals($order['access_token'], $token);
    }

    /** Можно ли сейчас оплатить заказ онлайн. */
    public static function canPay(array $order): bool
    {
        return $order['payment_method'] === 'card'
            && in_array($order['payment_status'], ['pending', 'failed'], true)
            && $order['status'] !== 'cancelled';
    }

    /** Строка «Слот 1 — Ocean, …» из снимка конфигурации. */
    public static function configSummary(array $config): string
    {
        return implode(', ', array_map(static fn ($s) => 'Слот ' . $s['slot'] . ' — ' . $s['name'], $config));
    }

    /** Заказы пользователя с количеством позиций. */
    public static function forUser(int $userId): array
    {
        return Database::fetchAll(
            'SELECT o.id, o.number, o.status, o.payment_status, o.payment_method, o.delivery_method,
                    o.total, o.created_at,
                    (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = o.id) AS items_count
               FROM orders o
              WHERE o.user_id = ?
              ORDER BY o.created_at DESC, o.id DESC',
            [$userId]
        );
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUSES[$status] ?? $status;
    }

    /** Модификатор цвета бейджа статуса. */
    public static function statusTone(string $status): string
    {
        return match ($status) {
            'new'       => 'info',
            'confirmed' => 'accent',
            'paid', 'shipped' => 'warning',
            'delivered' => 'success',
            'cancelled' => 'muted',
            default     => 'muted',
        };
    }
}
