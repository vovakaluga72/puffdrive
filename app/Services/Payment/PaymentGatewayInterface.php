<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Core\Request;

/**
 * Платёжный шлюз (эквайринг).
 *
 * Любой провайдер (имитация, bePaid, ЕРИП через банк и т.д.) реализует
 * этот интерфейс, а выбор активного шлюза делается в config.php:
 *     'payment' => ['gateway' => 'mock' | 'bepaid']
 * Остальной код магазина работает только с интерфейсом и не знает,
 * какой провайдер подключён.
 *
 * Жизненный цикл:
 *   1. createPayment() — регистрируем платёж у провайдера, получаем адрес
 *      платёжной страницы и перенаправляем туда покупателя;
 *   2. провайдер присылает уведомление (webhook) → handleCallback();
 *   3. при необходимости статус можно перепроверить → checkStatus().
 */
interface PaymentGatewayInterface
{
    /** Код провайдера для таблицы payments: 'mock', 'bepaid', 'erip'. */
    public function code(): string;

    /** Название для покупателя. */
    public function title(): string;

    /**
     * Создание платежа для заказа.
     * Сохраняет запись в payments (status = pending) и возвращает адрес,
     * куда нужно перенаправить покупателя для оплаты.
     *
     * @param array $order строка из таблицы orders
     * @return PaymentResult
     */
    public function createPayment(array $order): PaymentResult;

    /**
     * Обработка уведомления от провайдера (webhook / callback).
     * Обязана проверить подпись запроса! Возвращает обновлённый платёж или null.
     */
    public function handleCallback(Request $request): ?array;

    /**
     * Актуальный статус платежа у провайдера:
     * pending | success | failed | cancelled | refunded
     */
    public function checkStatus(string $transactionId): string;
}
