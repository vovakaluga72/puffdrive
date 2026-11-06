<?php
/**
 * Шапка личного кабинета: приветствие, счётчики и вкладки разделов.
 *
 * @var array  $user
 * @var array  $stats
 * @var string $section активная вкладка
 */
$tabs = [
    'profile'   => ['url' => '/account',           'title' => 'Профиль',  'icon' => 'user',    'count' => null],
    'orders'    => ['url' => '/account/orders',    'title' => 'Заказы',   'icon' => 'cart',    'count' => (int) ($stats['orders_count'] ?? 0)],
    'addresses' => ['url' => '/account/addresses', 'title' => 'Адреса',   'icon' => 'map-pin', 'count' => (int) ($stats['addresses_count'] ?? 0)],
    'reviews'   => ['url' => '/account/reviews',   'title' => 'Отзывы',   'icon' => 'star',    'count' => (int) ($stats['reviews_count'] ?? 0)],
];
?>
<header class="account-head">
    <div class="account-head__user">
        <span class="account-head__avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
        <div>
            <span class="eyebrow">Личный кабинет</span>
            <p class="account-head__name">Здравствуйте, <?= e($user['name']) ?></p>
            <p class="account-head__meta">
                <?= e($user['email']) ?> · с нами с <?= e(format_date($user['created_at'])) ?>
                <?php if ($user['role'] === 'admin'): ?>
                    · <span class="badge badge--accent">Администратор</span>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <form action="<?= e(url('/logout')) ?>" method="post" class="account-head__logout">
        <?= csrf_field() ?>
        <button class="btn btn--ghost btn--sm" type="submit"><?= icon('logout') ?> Выйти</button>
    </form>
</header>

<nav class="account-tabs" aria-label="Разделы личного кабинета">
    <?php foreach ($tabs as $key => $tab): ?>
        <a class="account-tabs__link<?= $section === $key ? ' is-active' : '' ?>" href="<?= e(url($tab['url'])) ?>"
           <?= $section === $key ? 'aria-current="page"' : '' ?>>
            <?= icon($tab['icon']) ?>
            <span><?= e($tab['title']) ?></span>
            <?php if ($tab['count'] !== null && $tab['count'] > 0): ?>
                <span class="account-tabs__count"><?= $tab['count'] ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>
