<?php
/**
 * Шапка сайта: верхняя строка с контактами, логотип, меню, поиск, кабинет, корзина.
 * На мобильных меню открывается выезжающей панелью.
 */

use App\Core\Auth;
use App\Core\View;

$nav = [
    ['url' => '/catalog',                       'title' => 'Каталог'],
    ['url' => '/catalog/zhidkosti',             'title' => 'Жидкости'],
    ['url' => '/catalog/apparaty',              'title' => 'Аппараты'],
    ['url' => '/catalog/komplektuyushchie',     'title' => 'Комплектующие'],
    ['url' => '/business',                      'title' => 'Бизнес'],
];
// «Каталог» подсвечиваем только на общей странице каталога, не на категориях
$isActive = static fn (string $path): bool => $path === '/catalog' ? current_path() === '/catalog' : is_active_path($path);
$user = Auth::user();
$cartCount = cart_count();
$phone = setting('phone');
?>
<header class="site-header" data-header>
    <div class="topbar">
        <div class="container topbar__inner">
            <span class="topbar__item"><?= icon('map-pin') ?> <?= e(setting('address')) ?></span>
            <span class="topbar__item topbar__item--hours"><?= icon('clock') ?> <?= e(setting('work_hours')) ?></span>
            <a class="topbar__item topbar__phone" href="<?= e(tel_href($phone)) ?>"><?= icon('phone') ?> <?= e(format_phone($phone)) ?></a>
        </div>
    </div>

    <div class="header-bar">
        <div class="container header-bar__inner">
            <button class="icon-btn header-bar__burger" type="button" aria-label="Открыть меню" aria-expanded="false" aria-controls="mobile-nav" data-nav-open>
                <?= icon('menu') ?>
            </button>

            <a class="header-bar__logo" href="<?= e(url('/')) ?>" aria-label="Puff Drive — на главную">
                <?= View::partial('partials/logo', ['size' => 'md']) ?>
            </a>

            <nav class="main-nav" aria-label="Основное меню">
                <ul class="main-nav__list">
                    <?php foreach ($nav as $item): ?>
                        <li>
                            <a class="main-nav__link<?= $isActive($item['url']) ? ' is-active' : '' ?>" href="<?= e(url($item['url'])) ?>"<?= $isActive($item['url']) ? ' aria-current="page"' : '' ?>>
                                <?= e($item['title']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <div class="header-bar__actions">
                <form class="header-search" action="<?= e(url('/search')) ?>" method="get" role="search" data-search data-suggest-url="<?= e(url('/search/suggest')) ?>">
                    <label class="visually-hidden" for="header-search-input">Поиск по магазину</label>
                    <input class="header-search__input" id="header-search-input" type="search" name="q"
                           placeholder="Поиск ароматов и товаров" autocomplete="off" maxlength="100"
                           aria-autocomplete="list" aria-controls="search-suggest" data-search-input>
                    <button class="header-search__submit" type="submit" aria-label="Найти"><?= icon('search') ?></button>
                    <div class="search-suggest" id="search-suggest" role="listbox" hidden data-search-suggest></div>
                </form>

                <button class="icon-btn header-bar__search-toggle" type="button" aria-label="Поиск" data-search-toggle>
                    <?= icon('search') ?>
                </button>

                <a class="icon-btn" href="<?= e(url($user ? '/account' : '/login')) ?>" aria-label="<?= $user ? 'Личный кабинет' : 'Войти' ?>" title="<?= $user ? e($user['name']) : 'Войти' ?>">
                    <?= icon('user') ?>
                </a>

                <a class="icon-btn icon-btn--cart" href="<?= e(url('/cart')) ?>" aria-label="Корзина, товаров: <?= $cartCount ?>">
                    <?= icon('cart') ?>
                    <span class="cart-badge<?= $cartCount > 0 ? '' : ' is-empty' ?>" data-cart-count><?= $cartCount ?></span>
                </a>
            </div>
        </div>
    </div>
</header>

<!-- Мобильное меню -->
<div class="mobile-nav" id="mobile-nav" aria-hidden="true" data-nav>
    <div class="mobile-nav__backdrop" data-nav-close></div>
    <div class="mobile-nav__panel" role="dialog" aria-modal="true" aria-label="Меню">
        <div class="mobile-nav__head">
            <?= View::partial('partials/logo', ['size' => 'sm']) ?>
            <button class="icon-btn" type="button" aria-label="Закрыть меню" data-nav-close><?= icon('close') ?></button>
        </div>

        <form class="mobile-nav__search" action="<?= e(url('/search')) ?>" method="get" role="search">
            <label class="visually-hidden" for="mobile-search-input">Поиск</label>
            <input class="input" id="mobile-search-input" type="search" name="q" placeholder="Поиск по магазину" maxlength="100">
            <button class="icon-btn" type="submit" aria-label="Найти"><?= icon('search') ?></button>
        </form>

        <ul class="mobile-nav__list">
            <?php foreach ($nav as $item): ?>
                <li><a class="mobile-nav__link<?= $isActive($item['url']) ? ' is-active' : '' ?>" href="<?= e(url($item['url'])) ?>"><?= e($item['title']) ?> <?= icon('chevron-right') ?></a></li>
            <?php endforeach; ?>
            <li><a class="mobile-nav__link" href="<?= e(url('/delivery')) ?>">Доставка и оплата <?= icon('chevron-right') ?></a></li>
            <li><a class="mobile-nav__link" href="<?= e(url('/contacts')) ?>">Контакты <?= icon('chevron-right') ?></a></li>
        </ul>

        <div class="mobile-nav__footer">
            <a class="btn btn--outline btn--block" href="<?= e(url($user ? '/account' : '/login')) ?>">
                <?= icon('user') ?> <?= $user ? 'Личный кабинет' : 'Войти или зарегистрироваться' ?>
            </a>
            <a class="mobile-nav__phone" href="<?= e(tel_href($phone)) ?>"><?= e(format_phone($phone)) ?></a>
            <span class="mobile-nav__hours"><?= e(setting('work_hours')) ?></span>
        </div>
    </div>
</div>
