<?php
/**
 * Личный кабинет — детали заказа.
 *
 * @var array $user
 * @var array $stats
 * @var array $order
 */

use App\Core\View;
?>
<section class="account">
    <div class="container">
        <?= View::partial('account/_nav', ['user' => $user, 'stats' => $stats, 'section' => 'orders']) ?>

        <a class="link-arrow account-back" href="<?= e(url('/account/orders')) ?>"><?= icon('chevron-left') ?> Все заказы</a>

        <?= View::partial('orders/_details', get_defined_vars()) ?>
    </div>
</section>
