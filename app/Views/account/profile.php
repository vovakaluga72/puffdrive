<?php
/**
 * Личный кабинет — профиль и смена пароля.
 *
 * @var array $user
 * @var array $stats
 * @var array $recentOrders
 */

use App\Core\View;
?>
<section class="account">
    <div class="container">
        <?= View::partial('account/_nav', ['user' => $user, 'stats' => $stats, 'section' => 'profile']) ?>

        <div class="account-stats">
            <div class="account-stat">
                <span class="account-stat__value"><?= (int) $stats['orders_count'] ?></span>
                <span class="account-stat__label"><?= plural((int) $stats['orders_count'], ['заказ', 'заказа', 'заказов']) ?></span>
            </div>
            <div class="account-stat">
                <span class="account-stat__value"><?= price_tag((float) $stats['orders_total'], 'price--inline') ?></span>
                <span class="account-stat__label">сумма покупок</span>
            </div>
            <div class="account-stat">
                <span class="account-stat__value"><?= (int) $stats['reviews_count'] ?></span>
                <span class="account-stat__label"><?= plural((int) $stats['reviews_count'], ['отзыв', 'отзыва', 'отзывов']) ?></span>
            </div>
        </div>

        <div class="account-grid">
            <!-- Личные данные -->
            <div class="panel" id="profile">
                <h2 class="panel__title">Личные данные</h2>
                <p class="panel__text">Используются для оформления заказов и связи с вами.</p>

                <form class="form" action="<?= e(url('/account/profile')) ?>" method="post" novalidate data-validate>
                    <?= csrf_field() ?>

                    <?= View::partial('partials/form-field', [
                        'name' => 'name', 'label' => 'Имя', 'required' => true, 'value' => $user['name'],
                        'autocomplete' => 'name', 'minlength' => 2, 'maxlength' => 100,
                    ]) ?>

                    <div class="form__grid">
                        <?= View::partial('partials/form-field', [
                            'name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'value' => $user['email'],
                            'autocomplete' => 'email', 'maxlength' => 190,
                            'data' => ['initial' => $user['email']],
                        ]) ?>
                        <?= View::partial('partials/form-field', [
                            'name' => 'phone', 'label' => 'Телефон', 'type' => 'tel', 'required' => true,
                            'value' => format_phone($user['phone']), 'autocomplete' => 'tel', 'inputmode' => 'tel',
                            'placeholder' => '+375 (29) 123-45-67', 'data' => ['phone' => ''],
                        ]) ?>
                    </div>

                    <div data-email-confirm <?= error('email_password') ? '' : 'hidden' ?>>
                        <?= View::partial('partials/form-field', [
                            'name' => 'email_password', 'label' => 'Текущий пароль',
                            'type' => 'password', 'autocomplete' => 'current-password',
                            'hint' => 'Нужен только при смене email',
                        ]) ?>
                    </div>

                    <button class="btn btn--primary" type="submit">Сохранить изменения</button>
                </form>
            </div>

            <!-- Смена пароля -->
            <div class="panel" id="password">
                <h2 class="panel__title">Смена пароля</h2>
                <p class="panel__text">После смены пароля вы останетесь в аккаунте на этом устройстве.</p>

                <form class="form" action="<?= e(url('/account/password')) ?>" method="post" novalidate data-validate>
                    <?= csrf_field() ?>
                    <input type="email" name="username" value="<?= e($user['email']) ?>" autocomplete="username" hidden>

                    <?= View::partial('partials/form-field', [
                        'name' => 'current_password', 'label' => 'Текущий пароль', 'type' => 'password', 'required' => true,
                        'autocomplete' => 'current-password',
                    ]) ?>
                    <?= View::partial('partials/form-field', [
                        'name' => 'password', 'label' => 'Новый пароль', 'type' => 'password', 'required' => true,
                        'autocomplete' => 'new-password', 'minlength' => 8, 'maxlength' => 72,
                        'hint' => 'Не меньше 8 символов, буквы и цифры', 'data' => ['password-strength' => ''],
                    ]) ?>
                    <?= View::partial('partials/form-field', [
                        'name' => 'password_confirmation', 'label' => 'Повторите новый пароль', 'type' => 'password', 'required' => true,
                        'autocomplete' => 'new-password', 'maxlength' => 72, 'data' => ['match' => 'password'],
                    ]) ?>

                    <button class="btn btn--outline" type="submit">Изменить пароль</button>
                </form>
            </div>

            <!-- Последние заказы -->
            <div class="panel panel--wide">
                <div class="panel__head">
                    <h2 class="panel__title">Последние заказы</h2>
                    <?php if ($recentOrders): ?>
                        <a class="link-arrow" href="<?= e(url('/account/orders')) ?>">Все заказы <?= icon('arrow-right') ?></a>
                    <?php endif; ?>
                </div>

                <?php if ($recentOrders): ?>
                    <ul class="order-list">
                        <?php foreach ($recentOrders as $order): ?>
                            <?= View::partial('account/_order-row', ['order' => $order]) ?>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty-state empty-state--compact">
                        <span class="empty-state__icon"><?= icon('cart') ?></span>
                        <p class="empty-state__text">Вы ещё ничего не заказывали. Начните с популярных ароматов!</p>
                        <a class="btn btn--primary btn--sm" href="<?= e(url('/catalog')) ?>">Перейти в каталог</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
