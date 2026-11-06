<?php
/**
 * Страница «Раздел в разработке».
 *
 * @var array $page ['title', 'icon', 'text', 'link' => [url, label]]
 */

use App\Core\View;

$phone = setting('phone');
?>
<section class="error-page soon-page">
    <div class="error-page__fog" aria-hidden="true"></div>
    <div class="container error-page__inner">
        <?= View::partial('partials/breadcrumbs', ['items' => [['title' => $page['title']]]]) ?>

        <span class="soon-page__icon"><?= icon($page['icon']) ?></span>
        <span class="eyebrow">Раздел в разработке</span>
        <h1 class="error-page__title"><?= e($page['title']) ?></h1>
        <p class="error-page__text"><?= e($page['text']) ?></p>

        <div class="error-page__actions">
            <a class="btn btn--primary" href="<?= e(url($page['link'][0])) ?>"><?= e($page['link'][1]) ?> <?= icon('arrow-right') ?></a>
            <a class="btn btn--ghost" href="<?= e(url('/')) ?>">На главную</a>
        </div>

        <ul class="soon-page__contacts">
            <li><?= icon('phone') ?><a href="<?= e(tel_href($phone)) ?>"><?= e(format_phone($phone)) ?></a></li>
            <li><?= icon('mail') ?><a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a></li>
            <li><?= icon('map-pin') ?><span><?= e(setting('address')) ?></span></li>
            <li><?= icon('clock') ?><span><?= e(setting('work_hours')) ?></span></li>
        </ul>
    </div>
</section>
