<?php
/**
 * Минимальный layout без обращений к БД — для страницы 500,
 * чтобы она показывалась, даже если упала база данных.
 *
 * @var string $content
 * @var string|null $title
 */
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <script>document.documentElement.classList.add('js')</script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e($title ?? 'Ошибка') ?> — Puff Drive</title>
    <link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600&family=Manrope:wght@400;600;700&display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="page-minimal">
    <main id="main" class="site-main">
        <?= $content ?>
    </main>
</body>
</html>
