<?php
/**
 * Страница 500. Рендерится в минимальном layout'е без запросов к БД.
 */
?>
<section class="error-page error-page--fullscreen">
    <div class="error-page__fog" aria-hidden="true"></div>
    <div class="container error-page__inner">
        <a class="error-page__logo" href="<?= e(url('/')) ?>" aria-label="Puff Drive — на главную">
            <?= App\Core\View::partial('partials/logo', ['size' => 'lg', 'tagline' => false]) ?>
        </a>
        <p class="error-page__code" aria-hidden="true">5<span>0</span>0</p>
        <h1 class="error-page__title">Что-то пошло не так</h1>
        <p class="error-page__text">
            На сервере произошла ошибка. Мы уже получили уведомление и разбираемся.
            Попробуйте обновить страницу через пару минут.
        </p>
        <div class="error-page__actions">
            <a class="btn btn--primary" href="<?= e(url('/')) ?>">На главную</a>
            <button class="btn btn--ghost" type="button" onclick="location.reload()"><?= icon('refresh') ?> Обновить</button>
        </div>
    </div>
</section>
