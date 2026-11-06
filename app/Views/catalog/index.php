<?php
/**
 * Каталог / категория / поиск.
 *
 * @var string                    $heading
 * @var string                    $description
 * @var array                     $breadcrumbs
 * @var App\Services\CatalogQuery $query
 * @var array                     $results
 * @var array                     $chips
 * @var array                     $facets
 * @var array                     $categories
 */

use App\Core\View;
use App\Services\CatalogQuery;

$category  = $query->category;
$isSearch  = $query->isSearchPage();
$formAction = $category ? url('/catalog/' . $category['slug']) : ($isSearch ? url('/search') : url('/catalog'));
$showVolumes = $facets['volumes'] && (!$category || $category['type'] === 'liquid');
$showAromas  = $facets['aromas'] && (!$category || $category['type'] === 'liquid');
$activeCount = $query->activeCount();
$visibleAromas = 8;
?>
<section class="catalog-hero">
    <div class="catalog-hero__fog" aria-hidden="true"></div>
    <div class="container">
        <?= View::partial('partials/breadcrumbs', ['items' => $breadcrumbs]) ?>

        <h1 class="catalog-hero__title"><?= e($heading) ?></h1>
        <?php if ($description !== ''): ?>
            <p class="catalog-hero__text"><?= e($description) ?></p>
        <?php endif; ?>

        <?php if ($isSearch): ?>
            <form class="catalog-search" action="<?= e(url('/search')) ?>" method="get" role="search">
                <label class="visually-hidden" for="catalog-search-input">Что ищем?</label>
                <?= icon('search') ?>
                <input class="catalog-search__input" id="catalog-search-input" type="search" name="q"
                       value="<?= e($query->q) ?>" placeholder="Аромат, товар или артикул" maxlength="100" autocomplete="off">
                <button class="btn btn--primary" type="submit">Найти</button>
            </form>
        <?php endif; ?>

        <nav class="category-tabs" aria-label="Категории">
            <a class="category-tabs__link<?= !$category && !$isSearch ? ' is-active' : '' ?>" href="<?= e(url('/catalog')) ?>">
                Все товары
            </a>
            <?php foreach ($categories as $cat): ?>
                <a class="category-tabs__link<?= $category && (int) $category['id'] === (int) $cat['id'] ? ' is-active' : '' ?>"
                   href="<?= e(url('/catalog/' . $cat['slug'])) ?>">
                    <?= e($cat['name']) ?> <span class="category-tabs__count"><?= (int) $cat['products_count'] ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
</section>

<section class="catalog">
    <div class="container catalog__inner">
        <!-- Фильтры. Обычная GET-форма: работает и без JavaScript -->
        <aside class="filters" id="catalog-filters-panel" data-filters aria-label="Фильтры">
            <div class="filters__backdrop" data-filters-close></div>
            <form class="filters__panel" id="catalog-filters" action="<?= e($formAction) ?>" method="get" data-filter-form>
                <div class="filters__head">
                    <h2 class="filters__title">Фильтры</h2>
                    <button class="icon-btn filters__close" type="button" aria-label="Закрыть фильтры" data-filters-close><?= icon('close') ?></button>
                </div>

                <?php if ($query->q !== ''): ?>
                    <input type="hidden" name="q" value="<?= e($query->q) ?>">
                <?php endif; ?>

                <fieldset class="filter-group">
                    <legend class="filter-group__title">Цена, BYN</legend>
                    <div class="price-range">
                        <label class="price-range__field">
                            <span class="visually-hidden">Цена от</span>
                            <input class="input input--sm" type="number" name="price_min" min="0" step="0.01" inputmode="decimal"
                                   value="<?= $query->priceMin !== null ? e($query->priceMin) : '' ?>"
                                   placeholder="от <?= e(number_format((float) $facets['price_min'], 0, '', ' ')) ?>">
                        </label>
                        <span class="price-range__dash" aria-hidden="true">—</span>
                        <label class="price-range__field">
                            <span class="visually-hidden">Цена до</span>
                            <input class="input input--sm" type="number" name="price_max" min="0" step="0.01" inputmode="decimal"
                                   value="<?= $query->priceMax !== null ? e($query->priceMax) : '' ?>"
                                   placeholder="до <?= e(number_format((float) $facets['price_max'], 0, '', ' ')) ?>">
                        </label>
                    </div>
                </fieldset>

                <?php if ($showVolumes): ?>
                    <fieldset class="filter-group">
                        <legend class="filter-group__title">Объём</legend>
                        <div class="toggle-chips">
                            <?php foreach ($facets['volumes'] as $volume): ?>
                                <label class="toggle-chip">
                                    <input type="checkbox" name="volume[]" value="<?= (int) $volume ?>"
                                           <?= in_array($volume, $query->volumes, true) ? 'checked' : '' ?>>
                                    <span><?= e(CatalogQuery::VOLUMES[$volume]) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                <?php endif; ?>

                <?php if ($showAromas): ?>
                    <fieldset class="filter-group">
                        <legend class="filter-group__title">Аромат</legend>
                        <ul class="aroma-filter" data-collapsible>
                            <?php foreach ($facets['aromas'] as $i => $aroma): ?>
                                <li<?= $i >= $visibleAromas && !in_array($aroma['slug'], $query->aromas, true) ? ' data-extra hidden' : '' ?>>
                                    <label class="aroma-filter__item">
                                        <input type="checkbox" name="aroma[]" value="<?= e($aroma['slug']) ?>"
                                               <?= in_array($aroma['slug'], $query->aromas, true) ? 'checked' : '' ?>>
                                        <span class="aroma-filter__box" aria-hidden="true"><?= icon('check') ?></span>
                                        <span class="aroma-dot" style="--dot: <?= e($aroma['color']) ?>"></span>
                                        <span class="aroma-filter__name"><?= e($aroma['name']) ?></span>
                                        <span class="aroma-filter__count"><?= (int) $aroma['products_count'] ?></span>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if (count($facets['aromas']) > $visibleAromas): ?>
                            <button class="filter-more" type="button" data-collapsible-toggle>
                                Показать все (<?= count($facets['aromas']) ?>) <?= icon('chevron-down') ?>
                            </button>
                        <?php endif; ?>
                    </fieldset>
                <?php endif; ?>

                <fieldset class="filter-group">
                    <legend class="filter-group__title">Дополнительно</legend>
                    <label class="switch">
                        <input type="checkbox" name="in_stock" value="1" <?= $query->inStock ? 'checked' : '' ?>>
                        <span class="switch__track" aria-hidden="true"></span>
                        <span>Только в наличии</span>
                    </label>
                    <label class="switch">
                        <input type="checkbox" name="sale" value="1" <?= $query->sale ? 'checked' : '' ?>>
                        <span class="switch__track" aria-hidden="true"></span>
                        <span>Со скидкой</span>
                    </label>
                </fieldset>

                <div class="filters__actions">
                    <button class="btn btn--primary btn--block" type="submit">
                        Показать <span data-results-total><?= (int) $results['total'] ?></span>
                    </button>
                    <a class="btn btn--ghost btn--block" href="<?= e($query->url(['price_min' => null, 'price_max' => null, 'volume' => null, 'aroma' => null, 'in_stock' => null, 'sale' => null, 'page' => null])) ?>" data-filters-reset>
                        Сбросить
                    </a>
                </div>
            </form>
        </aside>

        <div class="catalog__main">
            <div class="catalog-toolbar">
                <button class="btn btn--ghost btn--sm catalog-toolbar__filters" type="button" data-filters-open aria-controls="catalog-filters-panel">
                    <?= icon('filter') ?> Фильтры
                    <span class="catalog-toolbar__badge" data-filters-count<?= $activeCount ? '' : ' hidden' ?>><?= $activeCount ?></span>
                </button>

                <label class="sort-select">
                    <span class="sort-select__label">Сортировка:</span>
                    <select class="select select--sm" name="sort" form="catalog-filters" data-sort>
                        <?php if ($query->q !== ''): ?>
                            <option value="" <?= $query->sort === 'relevance' ? 'selected' : '' ?>>По релевантности</option>
                        <?php endif; ?>
                        <?php foreach (CatalogQuery::SORTS as $value => $label): ?>
                            <?php if ($value === 'popular' && $query->q === ''): ?>
                                <option value="" <?= $query->sort === 'popular' ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php else: ?>
                                <option value="<?= e($value) ?>" <?= $query->sort === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <div class="catalog-results" data-results aria-busy="false">
                <?= View::partial('catalog/_results', ['query' => $query, 'results' => $results, 'chips' => $chips]) ?>
            </div>
        </div>
    </div>
</section>
