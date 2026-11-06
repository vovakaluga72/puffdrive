<?php
/**
 * Личный кабинет — история заказов.
 *
 * @var array $user
 * @var array $stats
 * @var array $orders
 */

use App\Core\View;
?>
<section class="account">
    <div class="container">
        <?= View::partial('account/_nav', ['user' => $user, 'stats' => $stats, 'section' => 'orders']) ?>

        <div class="panel">
            <div class="panel__head">
                <h1 class="panel__title">Мои заказы</h1>
            </div>

            <?php if ($orders): ?>
                <ul class="order-list">
                    <?php foreach ($orders as $order): ?>
                        <?= View::partial('account/_order-row', ['order' => $order]) ?>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="empty-state">
                    <span class="empty-state__icon"><?= icon('cart') ?></span>
                    <h2 class="empty-state__title">Заказов пока нет</h2>
                    <p class="empty-state__text">
                        Здесь появятся все ваши покупки со статусами: от подтверждения до доставки.
                    </p>
                    <a class="btn btn--primary" href="<?= e(url('/catalog')) ?>">Выбрать аромат <?= icon('arrow-right') ?></a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
