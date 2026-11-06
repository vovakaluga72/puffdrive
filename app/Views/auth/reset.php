<?php
/**
 * Установка нового пароля по ссылке из письма.
 *
 * @var string     $token
 * @var array|null $reset запись восстановления (null — ссылка недействительна)
 */

use App\Core\View;
?>
<section class="auth">
    <div class="container auth__inner">
        <?= View::partial('auth/_aside', [
            'heading' => 'Новый пароль',
            'text'    => 'Придумайте надёжный пароль — после сохранения вы сразу войдёте в аккаунт.',
        ]) ?>

        <div class="auth-card">
            <?php if ($reset === null): ?>
                <div class="auth-card__state">
                    <span class="auth-card__state-icon auth-card__state-icon--error"><?= icon('alert') ?></span>
                    <h1 class="auth-card__title">Ссылка недействительна</h1>
                    <p class="auth-card__lead">
                        Срок действия ссылки истёк или она уже была использована. Запросите новую — это бесплатно и быстро.
                    </p>
                    <a class="btn btn--primary btn--block" href="<?= e(url('/password/forgot')) ?>">Запросить новую ссылку</a>
                </div>
            <?php else: ?>
                <h1 class="auth-card__title">Новый пароль</h1>
                <p class="auth-card__lead">Для аккаунта <strong><?= e($reset['email']) ?></strong></p>

                <form class="form" action="<?= e(url('/password/reset')) ?>" method="post" novalidate data-validate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <!-- Подсказка менеджерам паролей, для какого аккаунта пароль -->
                    <input type="email" name="username" value="<?= e($reset['email']) ?>" autocomplete="username" hidden>

                    <?= View::partial('partials/form-field', [
                        'name' => 'password', 'label' => 'Новый пароль', 'type' => 'password', 'required' => true,
                        'autocomplete' => 'new-password', 'minlength' => 8, 'maxlength' => 72,
                        'hint' => 'Не меньше 8 символов, буквы и цифры',
                        'data' => ['password-strength' => ''],
                    ]) ?>

                    <?= View::partial('partials/form-field', [
                        'name' => 'password_confirmation', 'label' => 'Повторите пароль', 'type' => 'password', 'required' => true,
                        'autocomplete' => 'new-password', 'maxlength' => 72,
                        'data' => ['match' => 'password'],
                    ]) ?>

                    <button class="btn btn--primary btn--lg btn--block" type="submit">
                        Сохранить и войти <?= icon('arrow-right') ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>
