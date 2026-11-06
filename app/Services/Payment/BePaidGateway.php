<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Core\Request;

/**
 * Заготовка подключения bePaid (https://bepaid.by) — популярного
 * белорусского эквайринга, который также умеет принимать оплату через ЕРИП.
 *
 * Как подключить (кратко, подробнее — README):
 *  1. Заключить договор с bePaid (или банком-партнёром), получить shop_id и secret_key.
 *  2. Указать их в config/config.php → payment.bepaid, переключить payment.gateway = 'bepaid'.
 *  3. В личном кабинете bePaid указать notification_url:
 *       https://ваш-домен/payment/callback/bepaid
 *  4. Реализовать методы ниже (запросы — через cURL, он есть на любом хостинге).
 *
 * TODO: реализовать после получения доступов. До этого шлюз не используется.
 */
final class BePaidGateway implements PaymentGatewayInterface
{
    public function code(): string
    {
        return 'bepaid';
    }

    public function title(): string
    {
        return 'bePaid';
    }

    /**
     * TODO: создать checkout-токен.
     *  POST {payment.bepaid.api_url} (https://checkout.bepaid.by/ctp/api/checkouts)
     *  Basic-авторизация: shop_id:secret_key, заголовок X-API-Version: 2
     *  Тело:
     *  {
     *    "checkout": {
     *      "test": <payment.bepaid.test_mode>,
     *      "transaction_type": "payment",
     *      "order": { "amount": <сумма в копейках>, "currency": "BYN",
     *                 "description": "Заказ PD-…", "tracking_id": "<номер заказа>" },
     *      "settings": {
     *        "success_url": "https://…/order/PD-…?token=…",
     *        "fail_url": "https://…/payment/PD-…?token=…",
     *        "notification_url": "https://…/payment/callback/bepaid",
     *        "language": "ru"
     *      }
     *    }
     *  }
     *  Ответ содержит checkout.token и checkout.redirect_url — сохранить token
     *  в payments.transaction_id и вернуть PaymentResult с redirect_url.
     */
    public function createPayment(array $order): PaymentResult
    {
        throw new \RuntimeException('Оплата через bePaid ещё не настроена. Укажите payment.gateway = "mock" в config.php.');
    }

    /**
     * TODO: обработать уведомление.
     *  1. Проверить подпись (заголовок Content-Signature, RSA публичный ключ магазина)
     *     или Basic-авторизацию shop_id:secret_key — без проверки запрос игнорировать!
     *  2. Найти платёж по transaction.tracking_id / uid.
     *  3. transaction.status: successful → 'success', failed → 'failed', incomplete → 'pending'.
     *  4. PaymentService::markPayment($paymentId, $status, $payload).
     */
    public function handleCallback(Request $request): ?array
    {
        return null;
    }

    /**
     * TODO: GET https://checkout.bepaid.by/ctp/api/checkouts/{token} → checkout.status
     */
    public function checkStatus(string $transactionId): string
    {
        return 'pending';
    }
}
