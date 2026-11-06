<?php
/**
 * Страница товара.
 *
 * @var array      $product
 * @var array      $variants
 * @var array      $selected     выбранный вариант
 * @var array      $images
 * @var array      $aromas       ароматы товара
 * @var string|null $mainColor
 * @var array      $reviews
 * @var array      $distribution
 * @var array      $similar
 * @var bool       $isMachine
 * @var array      $allAromas    справочник ароматов (для конфигуратора)
 * @var array      $fixedButtons
 * @var array      $breadcrumbs
 */

use App\Core\View;
use App\Models\Category;
use App\Models\Product;

$inStock  = $selected['stock'] > 0;
$discount = $selected['old_price'] ? discount_percent($selected['old_price'], $selected['price']) : 0;
$slots    = (int) $product['aroma_slots'];

// Данные вариантов для JS: цена (готовый HTML компонента цены), остаток, артикул
$variantsJs = array_map(static fn (array $v) => [
    'id'       => $v['id'],
    'sku'      => $v['sku'],
    'label'    => $v['label'],
    'stock'    => $v['stock'],
    'discount' => $v['old_price'] ? discount_percent($v['old_price'], $v['price']) : 0,
    'priceHtml' => price($v['price'], $v['old_price'], 'xl'),
    'saleEnds' => $v['sale_ends_at'] ? 'Акция действует до ' . format_date($v['sale_ends_at']) : '',
], $variants);

// Микроразметка Product для поисковиков
$ld = [
    '@context'    => 'https://schema.org',
    '@type'       => 'Product',
    'name'        => $product['name'],
    'description' => str_limit((string) ($product['short_description'] ?: $product['description']), 300),
    'sku'         => $selected['sku'],
    'category'    => $product['category_name'],
    'brand'       => ['@type' => 'Brand', 'name' => 'Puff Drive'],
    'offers'      => [
        '@type'         => 'AggregateOffer',
        'priceCurrency' => 'BYN',
        'lowPrice'      => number_format(min(array_column($variants, 'price')), 2, '.', ''),
        'highPrice'     => number_format(max(array_column($variants, 'price')), 2, '.', ''),
        'offerCount'    => count($variants),
        'availability'  => array_sum(array_column($variants, 'stock')) > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
    ],
];
if ($images) {
    $ld['image'] = array_map(static fn ($img) => absolute_url('/uploads/' . $img['path']), $images);
}
if ($product['rating_count'] > 0) {
    $ld['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => $product['rating_avg'], 'reviewCount' => $product['rating_count']];
}
?>
<article class="product" data-product="<?= (int) $product['id'] ?>">
    <div class="container">
        <?= View::partial('partials/breadcrumbs', ['items' => $breadcrumbs]) ?>

        <div class="product__layout">
            <!-- Галерея -->
            <div class="product-gallery" data-gallery>
                <div class="product-gallery__main">
                    <?php if ($images): ?>
                        <img src="<?= e(upload_url($images[0]['path'])) ?>" alt="<?= e($images[0]['alt'] ?: $product['name']) ?>"
                             data-gallery-main width="700" height="800" decoding="async">
                    <?php else: ?>
                        <?= placeholder_svg($product['category_type'], $mainColor, $aromas[0]['name'] ?? '', $slots) ?>
                    <?php endif; ?>
                    <span class="product-gallery__badges">
                        <span class="badge badge--sale" data-discount-badge<?= $discount ? '' : ' hidden' ?>>−<?= $discount ?>%</span>
                    </span>
                </div>
                <?php if (count($images) > 1): ?>
                    <div class="product-gallery__thumbs" role="list">
                        <?php foreach ($images as $i => $image): ?>
                            <button class="product-gallery__thumb<?= $i === 0 ? ' is-active' : '' ?>" type="button" role="listitem"
                                    data-gallery-thumb="<?= e(upload_url($image['path'])) ?>" data-alt="<?= e($image['alt'] ?: $product['name']) ?>"
                                    aria-label="Фото <?= $i + 1 ?>">
                                <img src="<?= e(upload_url($image['path'])) ?>" alt="" loading="lazy">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Информация и покупка -->
            <div class="product-info">
                <div class="product-info__meta">
                    <a class="product-info__category" href="<?= e(url('/catalog/' . $product['category_slug'])) ?>">
                        <?= e(Category::typeLabel($product['category_type']) ?: $product['category_name']) ?>
                    </a>
                    <?php foreach ($aromas as $aroma): ?>
                        <a class="aroma-tag" href="<?= e(url('/catalog/' . $product['category_slug'], ['aroma' => [$aroma['slug']]])) ?>">
                            <span class="aroma-dot" style="--dot: <?= e($aroma['color']) ?>"></span><?= e($aroma['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <h1 class="product-info__title"><?= e($product['name']) ?></h1>

                <div class="product-info__sub">
                    <?php if ($product['rating_count'] > 0): ?>
                        <a class="product-info__rating" href="#reviews">
                            <?= rating_stars($product['rating_avg']) ?>
                            <span><?= number_format($product['rating_avg'], 1, ',', '') ?></span>
                            <span class="text-muted">· <?= $product['rating_count'] ?> <?= plural($product['rating_count'], ['отзыв', 'отзыва', 'отзывов']) ?></span>
                        </a>
                    <?php else: ?>
                        <a class="product-info__rating text-muted" href="#reviews">Пока без отзывов</a>
                    <?php endif; ?>
                    <span class="product-info__sku">Артикул: <span data-sku><?= e($selected['sku']) ?></span></span>
                </div>

                <?php if ($product['short_description']): ?>
                    <p class="product-info__lead"><?= e($product['short_description']) ?></p>
                <?php endif; ?>

                <div class="product-info__price" data-price aria-live="polite">
                    <?= price($selected['price'], $selected['old_price'], 'xl') ?>
                </div>
                <p class="product-info__sale-ends" data-sale-ends<?= $selected['sale_ends_at'] ? '' : ' hidden' ?>>
                    <?= icon('clock') ?> <span><?= $selected['sale_ends_at'] ? 'Акция действует до ' . e(format_date($selected['sale_ends_at'])) : '' ?></span>
                </p>

                <form class="buy-form" id="buy-form" action="<?= e(url('/cart/add')) ?>" method="post" data-buy-form
                      data-variants="<?= json_attr($variantsJs) ?>">
                    <?= csrf_field() ?>

                    <?php if (count($variants) > 1): ?>
                        <fieldset class="variant-picker">
                            <legend class="variant-picker__title"><?= $product['category_type'] === 'liquid' ? 'Объём' : 'Комплектация' ?></legend>
                            <div class="variant-picker__options">
                                <?php foreach ($variants as $variant): ?>
                                    <label class="variant-option<?= $variant['stock'] <= 0 ? ' is-out' : '' ?>">
                                        <input type="radio" name="variant_id" value="<?= (int) $variant['id'] ?>"
                                               <?= $variant['id'] === $selected['id'] ? 'checked' : '' ?>>
                                        <span class="variant-option__body">
                                            <span class="variant-option__label"><?= e($variant['label']) ?></span>
                                            <span class="variant-option__price"><?= price_tag($variant['price'], 'price--inline') ?></span>
                                            <?php if ($variant['old_price']): ?>
                                                <span class="variant-option__sale">−<?= discount_percent($variant['old_price'], $variant['price']) ?>%</span>
                                            <?php endif; ?>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                    <?php else: ?>
                        <input type="hidden" name="variant_id" value="<?= (int) $selected['id'] ?>">
                    <?php endif; ?>

                    <p class="stock-line" data-stock>
                        <?php if ($inStock): ?>
                            <span class="stock-line__dot is-in"></span>
                            <?= $selected['stock'] <= 5 ? 'Осталось мало: ' . $selected['stock'] . ' шт.' : 'В наличии' ?>
                        <?php else: ?>
                            <span class="stock-line__dot"></span> Нет в наличии
                        <?php endif; ?>
                    </p>

                    <?php if ($isMachine): ?>
                        <div class="buy-form__slots" data-slot-inputs></div>
                        <p class="buy-form__hint" data-config-hint>
                            <?= icon('cog') ?>
                            <span>Чтобы добавить аппарат в корзину, выберите ароматы для всех слотов (<?= $slots ?>) в <a href="#configurator">конфигураторе ниже</a>.</span>
                        </p>
                    <?php endif; ?>

                    <div class="buy-form__row">
                        <div class="qty" data-qty>
                            <button class="qty__btn" type="button" aria-label="Меньше" data-qty-minus><?= icon('minus') ?></button>
                            <label class="visually-hidden" for="qty-input">Количество</label>
                            <input class="qty__input" id="qty-input" type="number" name="quantity" value="1" min="1"
                                   max="<?= max(1, min(99, $selected['stock'])) ?>" inputmode="numeric">
                            <button class="qty__btn" type="button" aria-label="Больше" data-qty-plus><?= icon('plus') ?></button>
                        </div>
                        <button class="btn btn--primary btn--lg buy-form__submit" type="submit" data-buy-submit
                                <?= !$inStock || $isMachine ? 'disabled' : '' ?>>
                            <?= icon('cart') ?> <span data-buy-label><?= $inStock ? 'В корзину' : 'Нет в наличии' ?></span>
                        </button>
                    </div>
                </form>

                <ul class="product-perks">
                    <li><?= icon('truck') ?> <span>Доставка по Беларуси: Европочта, Белпочта, курьер по Гродно. Бесплатно от <?= e(number_format((float) setting('free_delivery_threshold', '150'), 0, '', ' ')) ?>&nbsp;BYN</span></li>
                    <li><?= icon('coins') ?> <span>Оплата картой онлайн или при получении</span></li>
                    <?php if ($isMachine): ?>
                        <li><?= icon('shield') ?> <span>Гарантия, помощь с установкой и настройкой ароматов</span></li>
                    <?php else: ?>
                        <li><?= icon('map-pin') ?> <span>Самовывоз со склада в Гродно — бесплатно</span></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <?php if ($isMachine): ?>
        <?= View::partial('product/_configurator', [
            'product'      => $product,
            'allAromas'    => $allAromas,
            'fixedButtons' => $fixedButtons,
            'slots'        => $slots,
        ]) ?>
    <?php endif; ?>

    <div class="container">
        <div class="product-details">
            <section class="product-section" aria-labelledby="desc-title">
                <h2 class="product-section__title" id="desc-title">Описание</h2>
                <div class="prose"><?= nl2p((string) $product['description']) ?></div>
            </section>

            <?php if ($product['specs']): ?>
                <section class="product-section" aria-labelledby="specs-title">
                    <h2 class="product-section__title" id="specs-title">Характеристики</h2>
                    <dl class="specs">
                        <?php foreach ($product['specs'] as $spec): ?>
                            <div class="specs__row">
                                <dt><?= e($spec['name'] ?? '') ?></dt>
                                <dd><?= e($spec['value'] ?? '') ?></dd>
                            </div>
                        <?php endforeach; ?>
                        <div class="specs__row">
                            <dt>Артикул</dt>
                            <dd data-sku><?= e($selected['sku']) ?></dd>
                        </div>
                    </dl>
                </section>
            <?php endif; ?>
        </div>

        <?= View::partial('product/_reviews', [
            'product'      => $product,
            'reviews'      => $reviews,
            'distribution' => $distribution,
        ]) ?>

        <?php if ($similar): ?>
            <section class="product-similar" aria-labelledby="similar-title">
                <header class="section-head">
                    <h2 class="section-title" id="similar-title">Похожие товары</h2>
                    <a class="link-arrow" href="<?= e(url('/catalog/' . $product['category_slug'])) ?>">Все <?= e(mb_strtolower($product['category_name'])) ?> <?= icon('arrow-right') ?></a>
                </header>
                <div class="product-grid">
                    <?php foreach ($similar as $item): ?>
                        <?= View::partial('partials/product-card', ['product' => $item]) ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
</article>

<script type="application/ld+json"><?= json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
