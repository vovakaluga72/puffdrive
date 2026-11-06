<?php
/**
 * Хлебные крошки + разметка schema.org для поисковиков.
 *
 * @var array $items [['title' => 'Каталог', 'url' => '/catalog'], ['title' => 'Текущая']]
 */
$all = array_merge([['title' => 'Главная', 'url' => url('/')]], $items);
$ld = [
    '@context'        => 'https://schema.org',
    '@type'           => 'BreadcrumbList',
    'itemListElement' => [],
];
foreach ($all as $i => $item) {
    $ld['itemListElement'][] = array_filter([
        '@type'    => 'ListItem',
        'position' => $i + 1,
        'name'     => $item['title'],
        'item'     => isset($item['url']) ? absolute_url(substr($item['url'], strlen(base_path())) ?: '/') : null,
    ]);
}
?>
<nav class="breadcrumbs" aria-label="Навигационная цепочка">
    <ol class="breadcrumbs__list">
        <?php foreach ($all as $i => $item): ?>
            <li class="breadcrumbs__item">
                <?php if (isset($item['url']) && $i < count($all) - 1): ?>
                    <a href="<?= e($item['url']) ?>"><?= e($item['title']) ?></a>
                <?php else: ?>
                    <span aria-current="page"><?= e($item['title']) ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
<script type="application/ld+json"><?= json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
