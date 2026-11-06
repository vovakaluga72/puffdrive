<?php
/**
 * Основной layout публичной части.
 *
 * @var string      $content      HTML страницы
 * @var string|null $title        заголовок страницы
 * @var string|null $description  meta description
 * @var string|null $bodyClass    класс для <body>
 * @var array|null  $styles       дополнительные CSS из assets/css
 * @var array|null  $scripts      дополнительные JS из assets/js
 * @var string|null $htmlClass    класс для <html> (например, has-splash — блокировка прокрутки)
 */
$shopName  = setting('shop_name', 'Puff Drive');
$pageTitle = isset($title) && $title !== '' ? $title . ' — ' . $shopName : $shopName . ' — ' . setting('shop_tagline', 'Сухой туман');
$metaDesc  = $description ?? setting('meta_description');
?>
<!doctype html>
<html lang="ru"<?= !empty($htmlClass) ? ' class="' . e($htmlClass) . '"' : '' ?>>
<head>
    <meta charset="utf-8">
    <script>document.documentElement.classList.add('js')</script>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($metaDesc) ?>">
    <?php if (!empty($robots)): ?>
        <meta name="robots" content="<?= e($robots) ?>">
    <?php endif; ?>
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="theme-color" content="#0b0b0d">
    <meta name="color-scheme" content="dark">
    <link rel="canonical" href="<?= e(absolute_url(current_path())) ?>">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e($shopName) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($metaDesc) ?>">
    <meta property="og:locale" content="ru_BY">

    <link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Manrope:wght@300;400;500;600;700;800&display=swap">

    <link rel="stylesheet" href="<?= e(asset('css/tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <?php foreach ($styles ?? [] as $style): ?>
        <link rel="stylesheet" href="<?= e(asset('css/' . $style)) ?>">
    <?php endforeach; ?>
    <?= App\Core\View::stack('head') ?>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
    <a class="skip-link" href="#main">Перейти к содержимому</a>

    <?= App\Core\View::stack('body_start') ?>

    <?= App\Core\View::partial('partials/header') ?>

    <main id="main" class="site-main" tabindex="-1">
        <?= $content ?>
    </main>

    <?= App\Core\View::partial('partials/footer') ?>

    <?= App\Core\View::partial('partials/toasts') ?>

    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
    <?php foreach ($scripts ?? [] as $script): ?>
        <script src="<?= e(asset('js/' . $script)) ?>" defer></script>
    <?php endforeach; ?>
    <?= App\Core\View::stack('scripts') ?>
</body>
</html>
