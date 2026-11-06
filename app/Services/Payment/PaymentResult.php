<?php

declare(strict_types=1);

namespace App\Services\Payment;

/**
 * Результат создания платежа: куда отправить покупателя и ID транзакции.
 */
final class PaymentResult
{
    public function __construct(
        public readonly int $paymentId,
        public readonly string $transactionId,
        public readonly string $redirectUrl,
    ) {
    }
}
