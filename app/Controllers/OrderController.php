<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Models\Order;
use App\Models\User;

/**
 * Просмотр заказа: страница «Заказ оформлен» (доступ по секретной ссылке
 * или владельцу) и детали заказа в личном кабинете.
 */
final class OrderController extends Controller
{
    /** /order/{номер}?token=… — для гостей и сразу после оформления. */
    public function show(string $number): string
    {
        $order = Order::findByNumber($number);
        if ($order === null || !Order::canView($order, (string) $this->request->query('token', ''), Auth::id())) {
            $this->notFound(); // не раскрываем, существует ли заказ
        }

        return $this->view('orders/show', $this->data($order) + [
            'title'      => 'Заказ ' . $order['number'],
            'justPlaced' => Session::getFlash('order_placed') === $order['number'],
            'justPaid'   => (bool) Session::getFlash('order_paid', false),
            'bodyClass'  => 'page-order',
            'styles'     => ['cart.css'],
            'robots'     => 'noindex',
        ]);
    }

    /** /account/orders/{номер} — только для владельца. */
    public function accountShow(string $number): string
    {
        $order = Order::findByNumber($number);
        if ($order === null || (int) $order['user_id'] !== (int) Auth::id()) {
            $this->notFound();
        }

        $user = Auth::user();
        return $this->view('account/order', $this->data($order) + [
            'title'     => 'Заказ ' . $order['number'],
            'user'      => $user,
            'stats'     => User::stats((int) $user['id']),
            'section'   => 'orders',
            'bodyClass' => 'page-account',
            'styles'    => ['account.css', 'cart.css'],
        ]);
    }

    private function data(array $order): array
    {
        return [
            'order'   => $order,
            'items'   => Order::items((int) $order['id']),
            'history' => Order::history((int) $order['id']),
            'canPay'  => Order::canPay($order),
            'payUrl'  => url('/payment/' . $order['number'], ['token' => $order['access_token']]),
        ];
    }
}
