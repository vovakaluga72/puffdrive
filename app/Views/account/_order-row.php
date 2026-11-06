<?php
/**
 * Строка заказа в списке (кабинет). Вся строка — ссылка на детали заказа.
 *
 * @var array $order
 */

use App\Models\Order;
?>
<li>
    <a class="order-row" href="<?= e(url('/account/orders/' . $order['number'])) ?>">
        <div class="order-row__main">
            <span class="order-row__number">№ <?= e($order['number']) ?></span>
            <span class="order-row__date"><?= e(format_date($order['created_at'], true)) ?></span>
        </div>
        <div class="order-row__meta">
            <span class="status status--<?= e(Order::statusTone($order['status'])) ?>"><?= e(Order::statusLabel($order['status'])) ?></span>
            <?php if ($order['payment_status'] === 'paid' && $order['status'] !== 'paid'): ?>
                <span class="status status--success">Оплачен</span>
            <?php elseif ($order['payment_status'] === 'failed'): ?>
                <span class="status status--danger">Ошибка оплаты</span>
            <?php endif; ?>
            <span class="order-row__items">
                <?= (int) $order['items_count'] ?> <?= plural((int) $order['items_count'], ['товар', 'товара', 'товаров']) ?>
            </span>
        </div>
        <div class="order-row__total"><?= price((float) $order['total'], null, 'sm') ?></div>
    </a>
</li>
