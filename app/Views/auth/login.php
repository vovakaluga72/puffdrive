<?php
/** Страница входа. */

use App\Core\View;
?>
<section class="auth">
    <div class="container auth__inner">
        <?= View::partial('auth/_aside', [
            'heading' => 'С возвращением',
            'text'    => 'Войдите, чтобы видеть свои заказы, адреса и отзывы.',
        ]) ?>

        <div class="auth-card">
            <h1 class="auth-card__title">Вход</h1>
            <p class="auth-card__lead">Нет аккаунта? <a href="<?= e(url('/register')) ?>">Зарегистрируйтесь</a> — это займёт минуту.</p>

            <form class="form" action="<?= e(url('/login')) ?>" method="post" novalidate data-validate>
                <?= csrf_field() ?>

                <?= View::partial('partials/form-field', [
                    'name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true,
                    'autocomplete' => 'email', 'maxlength' => 190, 'placeholder' => 'you@example.by',
                ]) ?>

                <?= View::partial('partials/form-field', [
                    'name' => 'password', 'label' => 'Пароль', 'type' => 'password', 'required' => true,
                    'autocomplete' => 'current-password', 'maxlength' => 200,
                ]) ?>

                <div class="form__row form__row--end">
                    <a class="link-muted" href="<?= e(url('/password/forgot')) ?>">Забыли пароль?</a>
                </div>

                <button class="btn btn--primary btn--lg btn--block" type="submit">
                    Войти <?= icon('arrow-right') ?>
                </button>
            </form>

            <p class="auth-card__note">
                <?= icon('info') ?>
                Оформить заказ можно и без регистрации, но с аккаунтом вы увидите историю заказов и сохраните адреса.
            </p>
        </div>
    </div>
</section>
