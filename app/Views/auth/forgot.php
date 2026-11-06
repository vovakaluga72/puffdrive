<?php
/**
 * Запрос ссылки для восстановления пароля.
 *
 * @var bool $sent ссылка только что запрошена
 */

use App\Core\View;
?>
<section class="auth">
    <div class="container auth__inner">
        <?= View::partial('auth/_aside', [
            'heading' => 'Бывает с каждым',
            'text'    => 'Мы пришлём ссылку для создания нового пароля на вашу почту.',
        ]) ?>

        <div class="auth-card">
            <?php if ($sent): ?>
                <div class="auth-card__state">
                    <span class="auth-card__state-icon"><?= icon('mail') ?></span>
                    <h1 class="auth-card__title">Проверьте почту</h1>
                    <p class="auth-card__lead">
                        Если аккаунт с адресом <strong><?= e(old('email')) ?></strong> существует,
                        мы отправили на него ссылку для восстановления пароля.
                        Ссылка действует <?= (int) config('security.password_reset_ttl', 60) ?> минут.
                    </p>
                    <p class="auth-card__note">
                        <?= icon('info') ?>
                        Письма нет? Проверьте папку «Спам» или запросите ссылку ещё раз через пару минут.
                    </p>
                    <?php if (config('app.debug')): ?>
                        <p class="auth-card__note auth-card__note--dev">
                            <?= icon('alert') ?>
                            Режим отладки: ссылка также записана в журнал <code>storage/logs/app-<?= date('Y-m-d') ?>.log</code>.
                        </p>
                    <?php endif; ?>
                    <a class="btn btn--outline btn--block" href="<?= e(url('/login')) ?>">Вернуться ко входу</a>
                </div>
            <?php else: ?>
                <h1 class="auth-card__title">Восстановление пароля</h1>
                <p class="auth-card__lead">Укажите email, с которым вы регистрировались.</p>

                <form class="form" action="<?= e(url('/password/forgot')) ?>" method="post" novalidate data-validate>
                    <?= csrf_field() ?>

                    <?= View::partial('partials/form-field', [
                        'name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true,
                        'autocomplete' => 'email', 'maxlength' => 190, 'placeholder' => 'you@example.by',
                    ]) ?>

                    <button class="btn btn--primary btn--lg btn--block" type="submit">
                        Получить ссылку <?= icon('arrow-right') ?>
                    </button>
                </form>

                <p class="auth-card__lead auth-card__lead--center">
                    Вспомнили пароль? <a href="<?= e(url('/login')) ?>">Войти</a>
                </p>
            <?php endif; ?>
        </div>
    </div>
</section>
