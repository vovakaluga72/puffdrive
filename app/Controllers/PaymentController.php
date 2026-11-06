<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Core\Session;
use App\Models\Order;
use App\Services\Payment\MockGateway;
use App\Services\Payment\PaymentService;

/**
 * Страница оплаты (имитация) и приём уведомлений от платёжных систем.
 */
final class PaymentController extends Controller
{
    /** /payment/{номер}?token=… — тестовая платёжная форма. */
    public function show(string $number): string
    {
        $order = $this->orderOrFail($number, (string) $this->request->query('token', ''));
        $orderUrl = url('/order/' . $order['number'], ['token' => $order['access_token']]);

        if (!Order::canPay($order)) {
            $this->redirect($orderUrl);
        }

        $gateway = PaymentService::gateway();
        if (!$gateway instanceof MockGateway) {
            // Реальный шлюз: создаём платёж и уходим на страницу банка
            Response::redirect($gateway->createPayment($order)->redirectUrl);
        }

        // Для каждой попытки — свой платёж; используем ожидающий, если он есть
        $payment = Order::lastPayment((int) $order['id']);
        if ($payment === null || $payment['provider'] !== 'mock' || $payment['status'] !== 'pending') {
            $result  = $gateway->createPayment($order);
            $payment = Database::fetch('SELECT * FROM payments WHERE id = ?', [$result->paymentId]);
        }

        return $this->view('payment/mock', [
            'title'     => 'Оплата заказа ' . $order['number'],
            'order'     => $order,
            'payment'   => $payment,
            'orderUrl'  => $orderUrl,
            'bodyClass' => 'page-payment',
            'styles'    => ['cart.css'],
            'scripts'   => ['payment.js'],
            'robots'    => 'noindex',
        ]);
    }

    /** Нажатие «Успешная оплата» / «Ошибка оплаты» на тестовой странице. */
    public function mock(string $number): never
    {
        $token = $this->request->string('token');
        $order = $this->orderOrFail($number, $token);

        $payment = Database::fetch(
            "SELECT * FROM payments WHERE id = ? AND order_id = ? AND provider = 'mock' AND status = 'pending'",
            [$this->request->int('payment_id'), (int) $order['id']]
        );
        if ($payment === null || !Order::canPay($order)) {
            Session::flash('error', 'Платёж уже обработан или недоступен');
            $this->redirect('/order/' . $order['number'] . '?token=' . $order['access_token']);
        }

        $success = $this->request->string('result') === 'success';
        (new MockGateway())->complete((int) $payment['id'], $success);

        if ($success) {
            Session::flash('order_paid', true);
            Session::flash('success', 'Оплата прошла успешно. Спасибо!');
            Response::redirect(url('/order/' . $order['number'], ['token' => $order['access_token']]));
        }

        Session::flash('error', 'Банк отклонил платёж (тестовый режим). Попробуйте ещё раз или выберите оплату при получении.');
        Response::redirect(url('/payment/' . $order['number'], ['token' => $order['access_token']]));
    }

    /** Webhook платёжной системы: /payment/callback/{provider}. CSRF здесь отключён — проверяется подпись. */
    public function callback(string $provider): never
    {
        $gateway = PaymentService::gatewayByCode($provider);
        if ($gateway === null) {
            $this->notFound();
        }
        $gateway->handleCallback($this->request);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'OK';
        exit;
    }

    private function orderOrFail(string $number, string $token): array
    {
        $order = Order::findByNumber($number);
        if ($order === null || !Order::canView($order, $token, Auth::id())) {
            $this->notFound();
        }
        return $order;
    }
}
