<?php
/**
 * Декоративная панель рядом с формами входа/регистрации:
 * туман на Canvas, логотип и преимущества аккаунта.
 *
 * @var string $heading
 * @var string $text
 */

use App\Core\View;
?>
<aside class="auth-aside" aria-hidden="true">
    <canvas class="auth-aside__fog" data-fog data-fog-density="0.9"></canvas>
    <div class="auth-aside__content">
        <?= View::partial('partials/logo', ['size' => 'lg', 'tagline' => true]) ?>
        <p class="auth-aside__heading"><?= e($heading) ?></p>
        <p class="auth-aside__text"><?= e($text) ?></p>
        <ul class="auth-aside__list">
            <li><?= icon('check') ?> История заказов и статусы доставки</li>
            <li><?= icon('check') ?> Сохранённые адреса — оформление в пару кликов</li>
            <li><?= icon('check') ?> Отзывы с отметкой «Купил товар»</li>
            <li><?= icon('check') ?> Корзина сохраняется на всех устройствах</li>
        </ul>
    </div>
</aside>
