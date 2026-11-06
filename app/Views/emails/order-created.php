<?php
/**
 * Письмо покупателю: заказ оформлен.
 *
 * @var array  $order
 * @var array  $items
 * @var string $link
 */

use App\Models\Order;
use App\Services\DeliveryService;
use App\Services\Payment\PaymentService;
?>
<h1 style="margin:0 0 8px;font-family:Georgia,'Times New Roman',serif;font-size:24px;font-weight:normal;color:#f3efe8;">
    Спасибо за заказ!
</h1>
<p style="margin:0 0 24px;color:#a8a39b;">Заказ <strong style="color:#c9a96e;"><?= e($order['number']) ?></strong> от <?= e(format_date($order['created_at'], true)) ?></p>

<p style="margin:0 0 16px;">Здравствуйте, <?= e($order['customer_name']) ?>! Мы получили ваш заказ и скоро свяжемся для подтверждения.</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;border-collapse:collapse;">
    <?php foreach ($items as $item): ?>
        <tr>
            <td style="padding:10px 0;border-bottom:1px solid #26262c;font-size:14px;">
                <?= e($item['product_name']) ?><?= $item['variant_label'] ? ', ' . e($item['variant_label']) : '' ?> × <?= (int) $item['quantity'] ?>
                <?php if ($item['config']): ?>
                    <div style="font-size:12px;color:#8a857e;margin-top:4px;"><?= e(Order::configSummary($item['config'])) ?></div>
                <?php endif; ?>
            </td>
            <td align="right" style="padding:10px 0;border-bottom:1px solid #26262c;font-size:14px;white-space:nowrap;"><?= e(money((float) $item['line_total'])) ?></td>
        </tr>
    <?php endforeach; ?>
    <tr><td style="padding:6px 0;font-size:13px;color:#a8a39b;">Товары</td><td align="right" style="font-size:13px;color:#a8a39b;"><?= e(money((float) $order['subtotal'])) ?></td></tr>
    <?php if ((float) $order['discount_amount'] > 0): ?>
        <tr><td style="padding:6px 0;font-size:13px;color:#a8a39b;">Промокод <?= e($order['promo_code']) ?></td><td align="right" style="font-size:13px;color:#a8a39b;">−<?= e(money((float) $order['discount_amount'])) ?></td></tr>
    <?php endif; ?>
    <tr><td style="padding:6px 0;font-size:13px;color:#a8a39b;">Доставка</td><td align="right" style="font-size:13px;color:#a8a39b;"><?= (float) $order['delivery_cost'] > 0 ? e(money((float) $order['delivery_cost'])) : 'бесплатно' ?></td></tr>
    <tr><td style="padding:10px 0;font-size:16px;font-weight:bold;">Итого</td><td align="right" style="font-size:16px;font-weight:bold;color:#c9a96e;"><?= e(money((float) $order['total'])) ?></td></tr>
</table>

<p style="margin:0 0 6px;font-size:14px;"><strong>Доставка:</strong> <?= e(DeliveryService::title($order['delivery_method'])) ?></p>
<p style="margin:0 0 24px;font-size:14px;"><strong>Оплата:</strong> <?= e(PaymentService::title($order['payment_method'])) ?></p>

<p style="margin:0 0 8px;">
    <a href="<?= e($link) ?>" style="display:inline-block;padding:14px 28px;background:#c9a96e;color:#0b0b0d;font-weight:bold;text-decoration:none;border-radius:999px;">
        Посмотреть заказ
    </a>
</p>
