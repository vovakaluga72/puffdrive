<?php
/**
 * Детали заказа: статус, этапы, позиции (с конфигурацией аппарата), доставка, оплата, итоги.
 * Используется на странице «Заказ оформлен» и в личном кабинете.
 *
 * @var array  $order
 * @var array  $items
 * @var array  $history
 * @var bool   $canPay
 * @var string $payUrl
 */

use App\Models\Order;
use App\Services\DeliveryService;
use App\Services\Payment\PaymentService;

// Этапы заказа; у отменённого заказа шкалы нет
$steps = $order['status'] === 'cancelled'
    ? []
    : ['new' => 'Оформлен', 'confirmed' => 'Подтверждён', 'paid' => 'Оплачен', 'shipped' => 'Отправлен', 'delivered' => 'Доставлен'];
$current = array_search($order['status'], array_keys($steps), true);
$paymentTone = ['pending' => 'warning', 'paid' => 'success', 'failed' => 'danger', 'refunded' => 'muted'][$order['payment_status']] ?? 'muted';
?>
<div class="order-details">
    <div class="order-details__head">
        <div>
            <p class="order-details__number">Заказ <?= e($order['number']) ?></p>
            <p class="order-details__date">от <?= e(format_date($order['created_at'], true)) ?></p>
        </div>
        <div class="order-details__statuses">
            <span class="status status--<?= e(Order::statusTone($order['status'])) ?>"><?= e(Order::statusLabel($order['status'])) ?></span>
            <?php if (!($order['status'] === 'paid' && $order['payment_status'] === 'paid')): ?>
                <span class="status status--<?= e($paymentTone) ?>"><?= e(Order::PAYMENT_STATUSES[$order['payment_status']] ?? $order['payment_status']) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($steps): ?>
        <ol class="order-steps" aria-label="Этапы заказа">
            <?php $i = 0; foreach ($steps as $code => $label):
                $done = $current !== false && $i <= $current; ?>
                <li class="order-steps__item<?= $done ? ' is-done' : '' ?><?= $current === $i ? ' is-current' : '' ?>">
                    <span class="order-steps__dot"><?= $done ? icon('check') : '' ?></span>
                    <span class="order-steps__label"><?= e($label) ?></span>
                </li>
            <?php $i++; endforeach; ?>
        </ol>
    <?php else: ?>
        <div class="alert alert--error"><?= icon('alert') ?> <span>Заказ отменён. Если это ошибка — свяжитесь с нами: <?= e(format_phone(setting('phone'))) ?></span></div>
    <?php endif; ?>

    <?php if ($canPay): ?>
        <div class="order-pay">
            <div>
                <strong><?= $order['payment_status'] === 'failed' ? 'Оплата не прошла' : 'Заказ ожидает оплаты' ?></strong>
                <p>Оплатите заказ картой онлайн — это займёт минуту.</p>
            </div>
            <a class="btn btn--primary" href="<?= e($payUrl) ?>"><?= icon('coins') ?> Оплатить <?= price_tag((float) $order['total'], 'price--inline') ?></a>
        </div>
    <?php endif; ?>

    <div class="order-details__grid">
        <section class="order-block order-block--items" aria-label="Состав заказа">
            <h2 class="order-block__title">Состав заказа</h2>
            <ul class="order-items">
                <?php foreach ($items as $item): ?>
                    <li class="order-item">
                        <div class="order-item__info">
                            <?php if ($item['product_slug'] && (int) $item['product_active'] === 1): ?>
                                <a class="order-item__name" href="<?= e(url('/product/' . $item['product_slug'])) ?>"><?= e($item['product_name']) ?></a>
                            <?php else: ?>
                                <span class="order-item__name"><?= e($item['product_name']) ?></span>
                            <?php endif; ?>
                            <span class="order-item__meta"><?= e((string) $item['variant_label']) ?> · <?= e((string) $item['sku']) ?> · <?= price_tag((float) $item['price'], 'price--inline') ?> × <?= (int) $item['quantity'] ?></span>
                            <?php if ($item['config']): ?>
                                <ul class="cart-config">
                                    <?php foreach ($item['config'] as $slot): ?>
                                        <li><span class="aroma-dot" style="--dot: <?= e($slot['color'] ?? '#C9A96E') ?>"></span> Слот <?= (int) $slot['slot'] ?> — <?= e($slot['name']) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                        <span class="order-item__total"><?= price_tag((float) $item['line_total']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <dl class="summary-rows">
                <div class="summary-row"><dt>Товары</dt><dd><?= price_tag((float) $order['subtotal'], 'price--inline') ?></dd></div>
                <?php if ((float) $order['discount_amount'] > 0): ?>
                    <div class="summary-row summary-row--accent"><dt>Промокод <?= e((string) $order['promo_code']) ?></dt><dd>−<?= price_tag((float) $order['discount_amount'], 'price--inline') ?></dd></div>
                <?php endif; ?>
                <div class="summary-row"><dt>Доставка</dt><dd><?= (float) $order['delivery_cost'] > 0 ? price_tag((float) $order['delivery_cost'], 'price--inline') : 'Бесплатно' ?></dd></div>
            </dl>
            <div class="summary-total"><span>Итого</span><?= price((float) $order['total'], null, 'lg') ?></div>
        </section>

        <div class="order-details__side">
            <section class="order-block">
                <h2 class="order-block__title">Доставка</h2>
                <dl class="order-info">
                    <dt>Способ</dt><dd><?= e(DeliveryService::title($order['delivery_method'])) ?></dd>
                    <?php if ($order['delivery_method'] === 'pickup'): ?>
                        <dt>Адрес</dt><dd><?= e(setting('delivery_pickup_address')) ?></dd>
                    <?php endif; ?>
                    <?php if ($order['delivery_city']): ?><dt>Город</dt><dd><?= e($order['delivery_city']) ?></dd><?php endif; ?>
                    <?php if ($order['delivery_address']): ?><dt>Адрес</dt><dd><?= e($order['delivery_address']) ?></dd><?php endif; ?>
                    <?php if ($order['delivery_postcode']): ?><dt>Индекс</dt><dd><?= e($order['delivery_postcode']) ?></dd><?php endif; ?>
                    <?php if ($order['delivery_point']): ?><dt>Отделение</dt><dd><?= e($order['delivery_point']) ?></dd><?php endif; ?>
                </dl>
            </section>

            <section class="order-block">
                <h2 class="order-block__title">Покупатель и оплата</h2>
                <dl class="order-info">
                    <dt>Имя</dt><dd><?= e($order['customer_name']) ?></dd>
                    <dt>Телефон</dt><dd><?= e(format_phone($order['customer_phone'])) ?></dd>
                    <dt>Email</dt><dd><?= e($order['customer_email']) ?></dd>
                    <dt>Оплата</dt><dd><?= e(PaymentService::title($order['payment_method'])) ?></dd>
                    <?php if ($order['comment']): ?><dt>Комментарий</dt><dd><?= nl2br(e($order['comment'])) ?></dd><?php endif; ?>
                </dl>
            </section>

            <?php if ($history): ?>
                <section class="order-block">
                    <h2 class="order-block__title">История</h2>
                    <ul class="order-history">
                        <?php foreach ($history as $event): ?>
                            <li>
                                <span class="order-history__date"><?= e(format_date($event['created_at'], true)) ?></span>
                                <span><?= e(Order::statusLabel($event['status'])) ?><?= $event['comment'] ? ' — ' . e($event['comment']) : '' ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
        </div>
    </div>
</div>
