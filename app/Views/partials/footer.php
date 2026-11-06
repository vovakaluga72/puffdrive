<?php
/**
 * Футер: о магазине, навигация, документы, контакты, реквизиты.
 */

use App\Core\View;

$phone = setting('phone');
?>
<footer class="site-footer">
    <div class="site-footer__glow" aria-hidden="true"></div>
    <div class="container">
        <div class="site-footer__grid">
            <div class="site-footer__brand">
                <a href="<?= e(url('/')) ?>" aria-label="Puff Drive — на главную">
                    <?= View::partial('partials/logo', ['size' => 'md']) ?>
                </a>
                <p class="site-footer__about">
                    Жидкости, аппараты самообслуживания и комплектующие для бизнеса на «сухом тумане».
                    Доставка по всей Беларуси.
                </p>
                <?= View::partial('partials/socials', ['class' => 'site-footer__socials']) ?>
            </div>

            <nav class="site-footer__col" aria-label="Магазин">
                <h2 class="site-footer__title">Магазин</h2>
                <ul class="site-footer__links">
                    <li><a href="<?= e(url('/catalog/zhidkosti')) ?>">Жидкости</a></li>
                    <li><a href="<?= e(url('/catalog/apparaty')) ?>">Аппараты</a></li>
                    <li><a href="<?= e(url('/catalog/komplektuyushchie')) ?>">Комплектующие</a></li>
                    <li><a href="<?= e(url('/business')) ?>">Свой аппарат</a></li>
                </ul>
            </nav>

            <nav class="site-footer__col" aria-label="Покупателям">
                <h2 class="site-footer__title">Покупателям</h2>
                <ul class="site-footer__links">
                    <li><a href="<?= e(url('/delivery')) ?>">Доставка и оплата</a></li>
                    <li><a href="<?= e(url('/about')) ?>">О компании</a></li>
                    <li><a href="<?= e(url('/contacts')) ?>">Контакты</a></li>
                    <li><a href="<?= e(url('/privacy')) ?>">Политика конфиденциальности</a></li>
                    <li><a href="<?= e(url('/offer')) ?>">Публичная оферта</a></li>
                </ul>
            </nav>

            <div class="site-footer__col">
                <h2 class="site-footer__title">Контакты</h2>
                <ul class="site-footer__contacts">
                    <li><?= icon('phone') ?><a href="<?= e(tel_href($phone)) ?>"><?= e(format_phone($phone)) ?></a></li>
                    <li><?= icon('mail') ?><a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a></li>
                    <li><?= icon('map-pin') ?><span><?= e(setting('address')) ?></span></li>
                    <li><?= icon('clock') ?><span><?= e(setting('work_hours')) ?></span></li>
                </ul>
            </div>
        </div>

        <div class="site-footer__legal">
            <p>
                <?= e(setting('legal_name')) ?>, УНП <?= e(setting('legal_unp')) ?>.
                Юридический адрес: <?= e(setting('legal_address')) ?>.
                <?= e(setting('legal_trade_register')) ?>.
            </p>
        </div>

        <div class="site-footer__bottom">
            <span>© <?= date('Y') ?> <?= e(setting('shop_name', 'Puff Drive')) ?>. Все права защищены.</span>
            <a class="site-footer__splash-link" href="<?= e(url('/', ['splash' => 1])) ?>">
                <?= icon('sparkles') ?> Приветственный экран
            </a>
        </div>
    </div>
</footer>
