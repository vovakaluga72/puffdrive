<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Core\Database;
use App\Core\Logger;

/**
 * Точка входа в платёжную подсистему: выбор шлюза по конфигу
 * и общие операции над таблицами payments / orders.
 */
final class PaymentService
{
    /** Способы оплаты, которые видит покупатель при оформлении. */
    public static function methods(): array
    {
        $gateway = self::gateway();
        $methods = [
            'card' => [
                'title'       => 'Банковской картой онлайн',
                'description' => $gateway->code() === 'mock'
                    ? 'Visa, Mastercard, БЕЛКАРТ. Сейчас работает тестовый режим оплаты'
                    : 'Visa, Mastercard, БЕЛКАРТ — безопасная оплата на странице банка',
                'icon'        => 'coins',
                'enabled'     => true,
            ],
            'cod' => [
                'title'       => 'При получении',
                'description' => 'Наличными или картой курьеру, в отделении почты или при самовывозе',
                'icon'        => 'wallet',
                'enabled'     => true,
            ],
            'erip' => [
                'title'       => 'ЕРИП (Расчёт)',
                'description' => 'Оплата через интернет-банк или инфокиоск по номеру заказа',
                'icon'        => 'qr',
                // Место под ЕРИП: включается в config.php, когда будет договор с банком/bePaid
                'enabled'     => (bool) config('payment.erip.enabled', false),
            ],
        ];
        return $methods;
    }

    public static function isAvailable(string $method): bool
    {
        return (self::methods()[$method]['enabled'] ?? false) === true;
    }

    public static function title(string $method): string
    {
        return self::methods()[$method]['title'] ?? $method;
    }

    /** Активный шлюз онлайн-оплаты из конфига. */
    public static function gateway(): PaymentGatewayInterface
    {
        return match ((string) config('payment.gateway', 'mock')) {
            'bepaid' => new BePaidGateway(),
            default  => new MockGateway(),
        };
    }

    /** Шлюз по коду провайдера (для обработки callback). */
    public static function gatewayByCode(string $code): ?PaymentGatewayInterface
    {
        return match ($code) {
            'mock'   => new MockGateway(),
            'bepaid' => new BePaidGateway(),
            default  => null,
        };
    }

    /** Запись о платеже «при получении» — чтобы все заказы имели строку в payments. */
    public static function registerOffline(array $order): void
    {
        Database::insert('payments', [
            'order_id' => (int) $order['id'],
            'provider' => 'cod',
            'amount'   => $order['total'],
            'currency' => 'BYN',
            'status'   => 'pending',
        ]);
    }

    /**
     * Фиксирует результат платежа и обновляет заказ.
     * Вызывается шлюзами после подтверждения от провайдера.
     */
    public static function markPayment(int $paymentId, string $status, array $payload = []): void
    {
        Database::transaction(static function () use ($paymentId, $status, $payload): void {
            $payment = Database::fetch('SELECT * FROM payments WHERE id = ? FOR UPDATE', [$paymentId]);
            if ($payment === null || $payment['status'] === 'success') {
                return; // уже обработан (повторный webhook) — ничего не делаем
            }

            Database::update('payments', [
                'status'  => $status,
                'payload' => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : $payment['payload'],
            ], 'id = :id', ['id' => $paymentId]);

            $orderId = (int) $payment['order_id'];
            if ($status === 'success') {
                Database::query(
                    "UPDATE orders SET payment_status = 'paid',
                            status = CASE WHEN status IN ('new', 'confirmed') THEN 'paid' ELSE status END
                      WHERE id = ?",
                    [$orderId]
                );
                Database::insert('order_status_history', [
                    'order_id' => $orderId,
                    'status'   => 'paid',
                    'comment'  => 'Онлайн-оплата прошла успешно (' . $payment['provider'] . ')',
                ]);
            } elseif ($status === 'failed') {
                Database::query("UPDATE orders SET payment_status = 'failed' WHERE id = ? AND payment_status <> 'paid'", [$orderId]);
            }
        });

        Logger::info('Платёж обновлён', ['payment_id' => $paymentId, 'status' => $status]);
    }
}
