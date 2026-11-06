<?php
/**
 * Страница корзины. Содержимое (_body) перерисовывается через AJAX
 * при изменении количества, удалении и применении промокода.
 *
 * @var array $items
 * @var array $summary
 */

use App\Core\View;
?>
<section class="cart-page">
    <div class="container">
        <?= View::partial('partials/breadcrumbs', ['items' => [['title' => 'Корзина']]]) ?>
        <h1 class="page-title">Корзина</h1>

        <div class="cart" data-cart>
            <?= View::partial('cart/_body', ['items' => $items, 'summary' => $summary]) ?>
        </div>
    </div>
</section>
