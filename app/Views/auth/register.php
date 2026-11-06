<?php
/** Страница регистрации. */

use App\Core\View;

$consentError = error('consent');
?>
<section class="auth">
    <div class="container auth__inner">
        <?= View::partial('auth/_aside', [
            'heading' => 'Добро пожаловать',
            'text'    => 'Создайте аккаунт Puff Drive — для покупателей и владельцев аппаратов.',
        ]) ?>

        <div class="auth-card">
            <h1 class="auth-card__title">Регистрация</h1>
            <p class="auth-card__lead">Уже есть аккаунт? <a href="<?= e(url('/login')) ?>">Войдите</a></p>

            <form class="form" action="<?= e(url('/register')) ?>" method="post" novalidate data-validate>
                <?= csrf_field() ?>

                <!-- Ловушка для ботов: поле скрыто от людей -->
                <div class="hp-field" aria-hidden="true">
                    <label for="f-website">Не заполняйте это поле</label>
                    <input type="text" id="f-website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <?= View::partial('partials/form-field', [
                    'name' => 'name', 'label' => 'Имя', 'required' => true,
                    'autocomplete' => 'name', 'minlength' => 2, 'maxlength' => 100, 'placeholder' => 'Как к вам обращаться',
                ]) ?>

                <div class="form__grid">
                    <?= View::partial('partials/form-field', [
                        'name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true,
                        'autocomplete' => 'email', 'maxlength' => 190, 'placeholder' => 'you@example.by',
                    ]) ?>

                    <?= View::partial('partials/form-field', [
                        'name' => 'phone', 'label' => 'Телефон', 'type' => 'tel', 'required' => true,
                        'autocomplete' => 'tel', 'inputmode' => 'tel', 'placeholder' => '+375 (29) 123-45-67',
                        'data' => ['phone' => ''],
                    ]) ?>
                </div>

                <?= View::partial('partials/form-field', [
                    'name' => 'password', 'label' => 'Пароль', 'type' => 'password', 'required' => true,
                    'autocomplete' => 'new-password', 'minlength' => 8, 'maxlength' => 72,
                    'hint' => 'Не меньше 8 символов, буквы и цифры',
                    'data' => ['password-strength' => ''],
                ]) ?>

                <?= View::partial('partials/form-field', [
                    'name' => 'password_confirmation', 'label' => 'Повторите пароль', 'type' => 'password', 'required' => true,
                    'autocomplete' => 'new-password', 'maxlength' => 72,
                    'data' => ['match' => 'password'],
                ]) ?>

                <div class="field">
                    <label class="checkbox">
                        <input type="checkbox" name="consent" value="1" required <?= old('consent') ? 'checked' : '' ?>
                               aria-describedby="consent-error">
                        <span>
                            Я даю согласие на обработку персональных данных в соответствии с
                            <a href="<?= e(url('/privacy')) ?>" target="_blank">Политикой конфиденциальности</a>
                            (Закон Республики Беларусь № 99-З «О защите персональных данных»)
                        </span>
                    </label>
                    <span class="field__error" id="consent-error" role="alert"<?= $consentError ? '' : ' hidden' ?>><?= e($consentError ?? '') ?></span>
                </div>

                <button class="btn btn--primary btn--lg btn--block" type="submit">
                    Создать аккаунт <?= icon('arrow-right') ?>
                </button>
            </form>
        </div>
    </div>
</section>
