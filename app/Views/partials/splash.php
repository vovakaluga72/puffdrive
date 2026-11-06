<?php
/**
 * Приветственный экран (splash). Выводится поверх главной страницы,
 * поэтому весь контент главной остаётся в HTML и доступен поисковикам.
 * Закрывается кнопками, «Пропустить» или Esc; после закрытия ставится cookie на 30 дней.
 *
 * @var array $splashLines строки описания из настроек
 */

use App\Core\View;

$phone = setting('phone');
$lineIcons = ['droplet', 'machine', 'cog', 'truck'];
?>
<noscript><style>.splash{display:none!important}html.has-splash,html.has-splash body{overflow:auto!important}</style></noscript>

<div class="splash" id="splash" role="dialog" aria-modal="true" aria-labelledby="splash-title" data-splash>
    <canvas class="splash__fog" data-fog data-fog-density="1.25" aria-hidden="true"></canvas>
    <div class="splash__vignette" aria-hidden="true"></div>

    <button class="splash__skip" type="button" data-splash-close>
        Пропустить <kbd>Esc</kbd>
    </button>

    <div class="splash__inner">
        <div class="splash__brand splash-anim" style="--d: 0">
            <?= View::partial('partials/logo', ['size' => 'xl', 'tagline' => false]) ?>
        </div>

        <p class="splash__title splash-anim" id="splash-title" style="--d: 1">
            Puff <span>Drive</span>
        </p>
        <p class="splash__subtitle splash-anim" style="--d: 2"><?= e(setting('shop_tagline', 'Сухой туман')) ?></p>

        <p class="splash__slogan splash-anim" style="--d: 3">«<?= e(setting('splash_slogan', 'Аромат, который продаёт себя сам')) ?>»</p>

        <?php if ($splashLines): ?>
            <ul class="splash__lines splash-anim" style="--d: 4">
                <?php foreach (array_values($splashLines) as $i => $line): ?>
                    <li><?= icon($lineIcons[$i % count($lineIcons)]) ?><span><?= e($line) ?></span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <div class="splash__actions splash-anim" style="--d: 5">
            <a class="btn btn--primary btn--lg" href="<?= e(url('/')) ?>" data-splash-close>
                Войти в магазин <?= icon('arrow-right') ?>
            </a>
            <a class="btn btn--outline btn--lg" href="<?= e(url('/business')) ?>" data-splash-go>
                Хочу свой аппарат
            </a>
        </div>

        <div class="splash__contacts splash-anim" style="--d: 6">
            <a href="<?= e(tel_href($phone)) ?>"><?= icon('phone') ?> <?= e(format_phone($phone)) ?></a>
            <a href="mailto:<?= e(setting('email')) ?>"><?= icon('mail') ?> <?= e(setting('email')) ?></a>
            <span><?= icon('map-pin') ?> <?= e(setting('address')) ?></span>
            <span><?= icon('clock') ?> <?= e(setting('work_hours')) ?></span>
        </div>

        <div class="splash-anim" style="--d: 7">
            <?= View::partial('partials/socials', ['class' => 'socials--lg splash__socials']) ?>
        </div>
    </div>
</div>
