<?php
/**
 * Прочие HTTP-ошибки: 403, 405, 419 (устаревший CSRF-токен), 429.
 *
 * @var int    $status
 * @var string $message
 */
?>
<section class="error-page">
    <div class="error-page__fog" aria-hidden="true"></div>
    <div class="container error-page__inner">
        <p class="error-page__code error-page__code--sm" aria-hidden="true"><?= (int) $status ?></p>
        <h1 class="error-page__title"><?= $status === 419 ? 'Страница устарела' : 'Действие недоступно' ?></h1>
        <p class="error-page__text"><?= e($message) ?></p>
        <div class="error-page__actions">
            <?php if ($status === 419): ?>
                <button class="btn btn--primary" type="button" onclick="history.back()"><?= icon('chevron-left') ?> Вернуться назад</button>
            <?php endif; ?>
            <a class="btn btn--ghost" href="<?= e(url('/')) ?>">На главную</a>
        </div>
    </div>
</section>
