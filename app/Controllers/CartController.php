<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Core\View;
use App\Services\CartService;

/**
 * Корзина: просмотр, добавление, изменение количества, удаление, промокод.
 * Все изменения работают и через AJAX (JSON с обновлённым HTML), и обычной формой.
 */
final class CartController extends Controller
{
    public function index(): string
    {
        $items = CartService::items();

        return $this->view('cart/index', [
            'title'     => 'Корзина',
            'items'     => $items,
            'summary'   => CartService::summary($items),
            'bodyClass' => 'page-cart',
            'styles'    => ['cart.css'],
            'scripts'   => ['cart.js'],
            'robots'    => 'noindex',
        ]);
    }

    public function add(): never
    {
        $result = CartService::add(
            $this->request->int('variant_id'),
            $this->request->int('quantity', 1),
            $this->request->input('slots')
        );

        if ($this->request->wantsJson()) {
            $this->json($result, $result['ok'] ? 200 : 422);
        }

        // Без JS: обычная отправка формы и возврат на страницу товара
        Session::flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Товар добавлен в корзину' : $result['error']);
        $this->back('/catalog');
    }

    public function update(): never
    {
        $key = $this->request->string('key');
        // Кнопки «−» / «+» присылают delta, поле ввода — точное количество
        $delta = $this->request->int('delta', 0);
        $quantity = $delta !== 0
            ? CartService::quantityOf($key) + $delta
            : $this->request->int('quantity', 1);

        $result = CartService::update($key, $quantity);
        $this->respond($result['ok'] ? ($result['notice'] ?? null) : $result['error'], $result['ok'] ? 'info' : 'error');
    }

    public function remove(): never
    {
        CartService::remove($this->request->string('key'));
        $this->respond('Товар удалён из корзины', 'info');
    }

    public function applyPromo(): never
    {
        $code  = $this->request->string('code');
        $error = $code === '' ? 'Введите промокод' : CartService::applyPromo($code);
        $this->respond($error ?? 'Промокод применён', $error ? 'error' : 'success', $error === null);
    }

    public function removePromo(): never
    {
        CartService::removePromo();
        $this->respond('Промокод удалён', 'info');
    }

    /**
     * Ответ на изменение корзины: для AJAX — новый HTML корзины и счётчик,
     * для обычной формы — редирект обратно в корзину с сообщением.
     */
    private function respond(?string $message, string $type = 'info', bool $ok = true): never
    {
        if ($this->request->wantsJson()) {
            $items = CartService::items();
            $this->json([
                'ok'      => true,
                'success' => $ok && $type !== 'error',
                'message' => $message,
                'type'    => $type,
                'count'   => CartService::count(),
                'html'    => View::partial('cart/_body', ['items' => $items, 'summary' => CartService::summary($items)]),
            ]);
        }

        if ($message !== null) {
            Session::flash($type === 'error' ? 'error' : ($type === 'success' ? 'success' : 'info'), $message);
        }
        $this->redirect('/cart');
    }
}
