<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Core\Database;
use App\Core\Request;

/**
 * Имитация онлайн-оплаты (для учебного проекта и тестирования).
 *
 * Покупатель попадает на нашу же страницу /payment/{номер}, где видит
 * форму карты с тестовыми данными и две кнопки: «Успешная оплата»
 * и «Ошибка оплаты». Данные карты НЕ имеют атрибута name, поэтому
 * не отправляются на сервер и нигде не сохраняются.
 */
final class MockGateway implements PaymentGatewayInterface
{
    public function code(): string
    {
        return 'mock';
    }

    public function title(): string
    {
        return 'Тестовая оплата';
    }

    public function createPayment(array $order): PaymentResult
    {
        $transactionId = 'MOCK-' . strtoupper(bin2hex(random_bytes(6)));

        $paymentId = Database::insert('payments', [
            'order_id'       => (int) $order['id'],
            'provider'       => $this->code(),
            'transaction_id' => $transactionId,
            'amount'         => $order['total'],
            'currency'       => 'BYN',
            'status'         => 'pending',
        ]);

        return new PaymentResult(
            $paymentId,
            $transactionId,
            url('/payment/' . $order['number'], ['token' => $order['access_token']])
        );
    }

    /** Имитация не получает webhook — результат приходит с нашей же страницы. */
    public function handleCallback(Request $request): ?array
    {
        return null;
    }

    public function checkStatus(string $transactionId): string
    {
        return (string) (Database::fetchColumn(
            'SELECT status FROM payments WHERE provider = ? AND transaction_id = ?',
            [$this->code(), $transactionId]
        ) ?? 'pending');
    }

    /** Результат нажатия кнопки на тестовой странице оплаты. */
    public function complete(int $paymentId, bool $success): void
    {
        PaymentService::markPayment($paymentId, $success ? 'success' : 'failed', [
            'mode'   => 'mock',
            'result' => $success ? 'approved' : 'declined',
            'at'     => date('c'),
        ]);
    }
}
