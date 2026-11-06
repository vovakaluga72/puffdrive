<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Logger;
use App\Core\Response;
use App\Core\Session;
use App\Models\Address;
use App\Services\CartService;
use App\Services\DeliveryService;
use App\Services\OrderService;
use App\Services\Payment\PaymentService;

/**
 * Оформление заказа: контакты, доставка, оплата, согласие на обработку ПД.
 * Оформить заказ может и гость — тогда доступ к заказу по секретной ссылке.
 */
final class CheckoutController extends Controller
{
    private const LABELS = [
        'name'            => 'Имя и фамилия',
        'phone'           => 'Телефон',
        'email'           => 'Email',
        'delivery_method' => 'Способ доставки',
        'payment_method'  => 'Способ оплаты',
        'city'            => 'Город',
        'address'         => 'Адрес',
        'postcode'        => 'Индекс',
        'point'           => 'Отделение',
        'comment'         => 'Комментарий',
        'consent'         => 'Согласие на обработку персональных данных',
    ];

    public function show(): string
    {
        $items = CartService::items();
        $available = array_filter($items, static fn ($i) => $i['available']);
        if (!$available) {
            Session::flash('info', 'Корзина пуста — добавьте товары, чтобы оформить заказ');
            $this->redirect('/cart');
        }

        $user = Auth::user();
        $addresses = $user ? Address::forUser((int) $user['id']) : [];
        $default = $addresses[0] ?? null;

        return $this->view('checkout/index', [
            'title'      => 'Оформление заказа',
            'items'      => array_values($available),
            'summary'    => CartService::summary($items),
            'deliveries' => DeliveryService::methods(),
            'payments'   => PaymentService::methods(),
            'user'       => $user,
            'addresses'  => $addresses,
            'prefill'    => [
                'name'     => $user['name'] ?? '',
                'phone'    => $user ? format_phone($user['phone']) : '',
                'email'    => $user['email'] ?? '',
                'city'     => $default['city'] ?? '',
                'address'  => $default['address'] ?? '',
                'postcode' => $default['postcode'] ?? '',
            ],
            'bodyClass'  => 'page-checkout',
            'styles'     => ['cart.css'],
            'scripts'    => ['forms.js', 'checkout.js'],
            'robots'     => 'noindex',
        ]);
    }

    public function place(): never
    {
        $delivery = $this->request->string('delivery_method');
        $data = [
            'name'            => $this->request->string('name'),
            'phone'           => $this->request->string('phone'),
            'email'           => mb_strtolower($this->request->string('email')),
            'delivery_method' => $delivery,
            'payment_method'  => $this->request->string('payment_method'),
            'city'            => $this->request->string('city'),
            'address'         => $this->request->string('address'),
            'postcode'        => $this->request->string('postcode'),
            'point'           => $this->request->string('point'),
            'comment'         => $this->request->string('comment'),
            'consent'         => $this->request->input('consent'),
        ];

        // Обязательные поля адреса зависят от способа доставки
        $rules = [
            'name'            => 'required|min:2|max:100',
            'phone'           => 'required|phone',
            'email'           => 'required|email|max:190',
            'delivery_method' => 'required|in:' . implode(',', array_keys(DeliveryService::methods())),
            'payment_method'  => 'required|in:' . implode(',', array_keys(PaymentService::methods())),
            'comment'         => 'max:1000',
            'consent'         => 'accepted',
        ];
        $fields = DeliveryService::methods()[$delivery]['fields'] ?? [];
        if (in_array('city', $fields, true)) {
            $rules['city'] = 'required|min:2|max:100';
        }
        if (in_array('address', $fields, true)) {
            $rules['address'] = 'required|min:5|max:255';
        }
        if (in_array('postcode', $fields, true)) {
            $rules['postcode'] = 'required|regex:/^\d{6}$/';
        }
        if (in_array('point', $fields, true)) {
            $rules['point'] = 'required|min:2|max:255';
        }

        $valid = $this->validate($rules, self::LABELS, $data, [
            'postcode.regex' => 'Индекс в Беларуси — 6 цифр, например 230025',
            'delivery_method.in' => 'Выберите способ доставки',
            'payment_method.in'  => 'Выберите способ оплаты',
        ]);

        if (!PaymentService::isAvailable($valid['payment_method'])) {
            Session::flash('errors', ['payment_method' => 'Этот способ оплаты пока недоступен']);
            Session::flashInput($data);
            $this->redirect('/checkout');
        }

        // Лишние поля адреса (от другого способа доставки) не сохраняем
        $order = [
            'name'            => $valid['name'],
            'phone'           => $valid['phone'],
            'email'           => $valid['email'],
            'delivery_method' => $valid['delivery_method'],
            'payment_method'  => $valid['payment_method'],
            'city'            => $delivery === 'courier' ? 'Гродно' : (in_array('city', $fields, true) ? $valid['city'] : ''),
            'address'         => in_array('address', $fields, true) ? $valid['address'] : '',
            'postcode'        => in_array('postcode', $fields, true) ? $valid['postcode'] : '',
            'point'           => in_array('point', $fields, true) ? $valid['point'] : '',
            'comment'         => (string) $valid['comment'],
        ];

        $result = OrderService::place($order, $this->request->ip());
        if (!$result['ok']) {
            Session::flash('error', $result['error']);
            Session::flashInput($data);
            $this->redirect(str_contains($result['error'], 'Корзина пуста') ? '/cart' : '/checkout');
        }

        $placed = $result['order'];
        $orderUrl = url('/order/' . $placed['number'], ['token' => $placed['access_token']]);
        Session::flash('order_placed', $placed['number']);

        // Онлайн-оплата: переходим на страницу платёжного шлюза
        if ($placed['payment_method'] === 'card') {
            try {
                $payment = PaymentService::gateway()->createPayment($placed);
                Response::redirect($payment->redirectUrl);
            } catch (\RuntimeException $e) {
                Logger::error('Не удалось создать платёж', ['order' => $placed['number'], 'error' => $e->getMessage()]);
                Session::flash('error', 'Заказ оформлен, но онлайн-оплата сейчас недоступна. Менеджер свяжется с вами.');
            }
        }

        Response::redirect($orderUrl);
    }
}
