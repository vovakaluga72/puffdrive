<?php
/**
 * Блок результатов каталога. Рендерится и в странице, и отдельно для AJAX.
 *
 * @var App\Services\CatalogQuery $query
 * @var array                     $results ['items', 'total', 'page', 'pages']
 * @var array                     $chips   активные фильтры
 */

use App\Core\View;

$total = $results['total'];
?>
<div class="results-head">
    <p class="results-count" aria-live="polite">
        <?php if ($query->q !== ''): ?>
            По запросу «<strong><?= e($query->q) ?></strong>»
            <?= $total > 0 ? 'найдено' : 'ничего не найдено' ?>
            <?php if ($total > 0): ?><strong><?= $total ?></strong> <?= plural($total, ['товар', 'товара', 'товаров']) ?><?php endif; ?>
        <?php else: ?>
            <strong><?= $total ?></strong> <?= plural($total, ['товар', 'товара', 'товаров']) ?>
        <?php endif; ?>
    </p>

    <?php if ($chips): ?>
        <ul class="filter-chips">
            <?php foreach ($chips as $chip): ?>
                <li>
                    <a class="filter-chip" href="<?= e($chip['url']) ?>" data-ajax-link aria-label="Убрать фильтр: <?= e($chip['label']) ?>">
                        <?= e($chip['label']) ?> <?= icon('close') ?>
                    </a>
                </li>
            <?php endforeach; ?>
            <li>
                <a class="filter-chip filter-chip--reset" href="<?= e($query->url(['price_min' => null, 'price_max' => null, 'volume' => null, 'aroma' => null, 'in_stock' => null, 'sale' => null, 'page' => null])) ?>" data-ajax-link>
                    Сбросить все
                </a>
            </li>
        </ul>
    <?php endif; ?>
</div>

<?php if ($results['items']): ?>
    <div class="product-grid product-grid--catalog">
        <?php foreach ($results['items'] as $product): ?>
            <?= View::partial('partials/product-card', ['product' => $product]) ?>
        <?php endforeach; ?>
    </div>

    <?= View::partial('partials/pagination', [
        'page'  => $results['page'],
        'pages' => $results['pages'],
        'link'  => static fn (int $page): string => $query->url(['page' => $page > 1 ? $page : null]),
    ]) ?>
<?php else: ?>
    <div class="empty-state">
        <span class="empty-state__icon"><?= icon($query->q !== '' ? 'search' : 'filter') ?></span>
        <h2 class="empty-state__title"><?= $query->q !== '' ? 'Ничего не нашлось' : 'Нет товаров с такими параметрами' ?></h2>
        <p class="empty-state__text">
            <?php if ($query->q !== ''): ?>
                Проверьте написание или попробуйте другой запрос: например, название аромата (Морской бриз, Sauvage) или «форсунка».
            <?php else: ?>
                Попробуйте смягчить условия: расширить диапазон цены или убрать часть фильтров.
            <?php endif; ?>
        </p>
        <?php if ($query->hasFilters()): ?>
            <a class="btn btn--outline" href="<?= e($query->url(['price_min' => null, 'price_max' => null, 'volume' => null, 'aroma' => null, 'in_stock' => null, 'sale' => null, 'page' => null])) ?>" data-ajax-link>Сбросить фильтры</a>
        <?php else: ?>
            <a class="btn btn--outline" href="<?= e(url('/catalog')) ?>">Весь каталог</a>
        <?php endif; ?>
    </div>
<?php endif; ?>
