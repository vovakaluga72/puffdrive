<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Models\Order;
use App\Services\Payment\PaymentService;

/**
 * Оформление заказа.
 *
 * Всё происходит в одной транзакции:
 *  1. блокируем строки вариантов (SELECT … FOR UPDATE) и проверяем остатки;
 *  2. пересчитываем суммы на сервере (клиенту не доверяем);
 *  3. создаём заказ и позиции со «снимком» названия, цены и конфигурации;
 *  4. списываем остатки, увеличиваем счётчик продаж, засчитываем промокод;
 *  5. очищаем корзину.
 * Если на любом шаге ошибка — откат, заказ не создаётся.
 */
final class OrderService
{
    /**
     * @param array $data проверенные данные формы оформления
     * @return array{ok: bool, error?: string, order?: array}
     */
    public static function place(array $data, string $ip): array
    {
        $items = CartService::items();
        $items = array_values(array_filter($items, static fn ($i) => $i['available']));
        if (!$items) {
            return ['ok' => false, 'error' => 'Корзина пуста или товары закончились'];
        }

        try {
            $order = Database::transaction(static function () use ($items, $data, $ip): array {
                // 1. Блокировка и проверка остатков
                $ids = array_values(array_unique(array_column($items, 'variant_id')));
                $placeholders = implode(', ', array_fill(0, count($ids), '?'));
                $stocks = array_column(Database::fetchAll(
                    "SELECT id, stock FROM product_variants WHERE id IN ({$placeholders}) FOR UPDATE",
                    $ids
                ), 'stock', 'id');

                $need = [];
                foreach ($items as $item) {
                    $need[$item['variant_id']] = ($need[$item['variant_id']] ?? 0) + $item['quantity'];
                }
                foreach ($items as $item) {
                    if ((int) ($stocks[$item['variant_id']] ?? 0) < $need[$item['variant_id']]) {
                        throw new OrderException(
                            "«{$item['name']}» ({$item['label']}) осталось только " . (int) ($stocks[$item['variant_id']] ?? 0)
                            . ' шт. Уменьшите количество в корзине.'
                        );
                    }
                }

                // 2. Суммы
                $summary  = CartService::summary(CartService::items());
                $subtotal = $summary['subtotal'];
                $discount = $summary['discount'];
                $promo    = $summary['promo_error'] === null ? $summary['promo'] : null;
                $delivery = DeliveryService::cost($data['delivery_method'], $subtotal - $discount);
                $total    = round($subtotal - $discount + $delivery, 2);

                // 3. Заказ
                $orderId = Database::insert('orders', [
                    'number'                   => 'TMP-' . bin2hex(random_bytes(6)),
                    'access_token'             => bin2hex(random_bytes(16)),
                    'user_id'                  => Auth::id(),
                    'status'                   => 'new',
                    'payment_method'           => $data['payment_method'],
                    'payment_status'           => 'pending',
                    'delivery_method'          => $data['delivery_method'],
                    'customer_name'            => $data['name'],
                    'customer_phone'           => $data['phone'],
                    'customer_email'           => $data['email'],
                    'delivery_city'            => $data['city'] ?: null,
                    'delivery_address'         => $data['address'] ?: null,
                    'delivery_postcode'        => $data['postcode'] ?: null,
                    'delivery_point'           => $data['point'] ?: null,
                    'comment'                  => $data['comment'] ?: null,
                    'subtotal'                 => $subtotal,
                    'discount_amount'          => $discount,
                    'delivery_cost'            => $delivery,
                    'total'                    => $total,
                    'promo_code_id'            => $promo ? (int) $promo['id'] : null,
                    'promo_code'               => $promo ? $promo['code'] : null,
                    'personal_data_consent_at' => date('Y-m-d H:i:s'),
                    'ip'                       => $ip,
                ]);
                // Читаемый номер: PD-ГГММДД-ID
                $number = 'PD-' . date('ymd') . '-' . str_pad((string) $orderId, 4, '0', STR_PAD_LEFT);
                Database::update('orders', ['number' => $number], 'id = :id', ['id' => $orderId]);

                // 4. Позиции, списание остатков, продажи
                foreach ($items as $item) {
                    $options = null;
                    if ($item['config']) {
                        // Снимок конфигурации с названиями ароматов на момент покупки
                        $options = ['slots' => array_map(
                            static fn ($s) => ['slot' => $s['slot'], 'id' => $s['id'], 'name' => $s['name'], 'color' => $s['color']],
                            $item['config']
                        )];
                    }

                    Database::insert('order_items', [
                        'order_id'      => $orderId,
                        'product_id'    => $item['product_id'],
                        'variant_id'    => $item['variant_id'],
                        'product_name'  => $item['name'],
                        'variant_label' => $item['label'],
                        'sku'           => $item['sku'],
                        'price'         => $item['price'],
                        'quantity'      => $item['quantity'],
                        'line_total'    => $item['line_total'],
                        'options'       => $options ? json_encode($options, JSON_UNESCAPED_UNICODE) : null,
                    ]);

                    $updated = Database::query(
                        'UPDATE product_variants SET stock = stock - ? WHERE id = ? AND stock >= ?',
                        [$item['quantity'], $item['variant_id'], $item['quantity']]
                    )->rowCount();
                    if ($updated !== 1) {
                        throw new OrderException("Товар «{$item['name']}» закончился. Обновите корзину.");
                    }
                    Database::query('UPDATE products SET sales_count = sales_count + ? WHERE id = ?', [$item['quantity'], $item['product_id']]);
                }

                if ($promo && !PromoService::consume((int) $promo['id'])) {
                    throw new OrderException('Промокод только что закончился. Удалите его из корзины и оформите заказ ещё раз.');
                }

                Database::insert('order_status_history', [
                    'order_id' => $orderId,
                    'status'   => 'new',
                    'comment'  => 'Заказ оформлен на сайте',
                ]);

                return Order::find($orderId);
            });
        } catch (OrderException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        CartService::clear();

        // Платёж: онлайн — через шлюз, иначе — запись «при получении»
        if ($order['payment_method'] !== 'card') {
            PaymentService::registerOffline($order);
        }

        self::notify($order);
        return ['ok' => true, 'order' => $order];
    }

    /** Письмо покупателю о новом заказе. */
    private static function notify(array $order): void
    {
        $link = absolute_url('/order/' . $order['number']) . '?token=' . $order['access_token'];
        $sent = Mailer::send($order['customer_email'], 'Заказ ' . $order['number'] . ' оформлен — Puff Drive', 'order-created', [
            'order' => $order,
            'items' => Order::items((int) $order['id']),
            'link'  => $link,
        ]);
        Logger::info('Новый заказ', [
            'number' => $order['number'],
            'total'  => $order['total'],
            'mail'   => $sent ? 'отправлено' : 'не отправлено',
        ]);
    }
}
