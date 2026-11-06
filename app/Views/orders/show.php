<?php
/**
 * Страница заказа по секретной ссылке («Заказ оформлен» / «Оплачено»).
 *
 * @var array $order
 * @var bool  $justPlaced
 * @var bool  $justPaid
 */

use App\Core\Auth;
use App\Core\View;
?>
<section class="order-page">
    <div class="container">
        <?php if ($justPlaced || $justPaid): ?>
            <div class="order-success">
                <div class="order-success__fog" aria-hidden="true"></div>
                <span class="order-success__icon"><?= icon('check') ?></span>
                <h1 class="order-success__title"><?= $justPaid ? 'Оплата прошла успешно' : 'Заказ оформлен' ?></h1>
                <p class="order-success__text">
                    Спасибо, <?= e($order['customer_name']) ?>! Номер заказа — <strong><?= e($order['number']) ?></strong>.
                    <?php if ($order['delivery_method'] === 'pickup'): ?>
                        Мы позвоним, когда заказ будет готов к выдаче.
                    <?php else: ?>
                        Менеджер свяжется с вами для подтверждения в рабочее время.
                    <?php endif; ?>
                </p>
                <p class="order-success__note">Подтверждение и ссылка на заказ отправлены на <?= e($order['customer_email']) ?></p>
                <div class="form__actions">
                    <a class="btn btn--outline" href="<?= e(url('/catalog')) ?>">Продолжить покупки</a>
                    <?php if (Auth::check()): ?>
                        <a class="btn btn--ghost" href="<?= e(url('/account/orders')) ?>">Мои заказы</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <?= View::partial('partials/breadcrumbs', ['items' => [['title' => 'Заказ ' . $order['number']]]]) ?>
            <h1 class="page-title">Заказ <?= e($order['number']) ?></h1>
        <?php endif; ?>

        <?php if (!Auth::check()): ?>
            <p class="order-page__guest">
                <?= icon('info') ?>
                Сохраните ссылку на эту страницу — по ней можно следить за заказом.
                <a href="<?= e(url('/register')) ?>">Зарегистрируйтесь</a>, чтобы видеть все заказы в личном кабинете.
            </p>
        <?php endif; ?>

        <?= View::partial('orders/_details', get_defined_vars()) ?>
    </div>
</section>
