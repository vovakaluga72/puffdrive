<?php
/**
 * Карточка товара для сеток (главная, каталог, поиск, похожие товары).
 *
 * @var array $product строка из Product::cardSelect()
 */

use App\Models\Category;
use App\Models\Product;

$href      = Product::url($product);
$inStock   = $product['total_stock'] > 0;
$discount  = $product['max_discount'];
$isMachine = $product['category_type'] === 'machine';
$isNew     = strtotime($product['created_at']) > strtotime('-30 days');
?>
<article class="product-card reveal" data-product-id="<?= (int) $product['id'] ?>">
    <a class="product-card__media" href="<?= e($href) ?>" tabindex="-1" aria-hidden="true">
        <?php if ($product['image']): ?>
            <img src="<?= e(upload_url($product['image'])) ?>" alt="" loading="lazy" decoding="async">
        <?php else: ?>
            <?= placeholder_svg($product['category_type'], $product['aroma_color'], $product['aroma_name'] ?? '', (int) $product['aroma_slots']) ?>
        <?php endif; ?>

        <span class="product-card__badges">
            <?php if ($discount > 0): ?>
                <span class="badge badge--sale">−<?= $discount ?>%</span>
            <?php endif; ?>
            <?php if ($isNew): ?>
                <span class="badge badge--new">Новинка</span>
            <?php endif; ?>
            <?php if ($isMachine): ?>
                <span class="badge badge--accent"><?= (int) $product['aroma_slots'] ?> <?= plural((int) $product['aroma_slots'], ['аромат', 'аромата', 'ароматов']) ?></span>
            <?php endif; ?>
        </span>
    </a>

    <div class="product-card__body">
        <div class="product-card__meta">
            <span class="product-card__category">
                <?php if ($product['aroma_color']): ?>
                    <span class="aroma-dot" style="--dot: <?= e($product['aroma_color']) ?>"></span>
                <?php endif; ?>
                <?= e($product['aroma_name'] ?: Category::typeLabel($product['category_type'])) ?>
            </span>
            <?php if ($product['rating_count'] > 0): ?>
                <span class="product-card__rating" title="<?= $product['rating_count'] ?> <?= plural($product['rating_count'], ['отзыв', 'отзыва', 'отзывов']) ?>">
                    <?= icon('star') ?> <?= number_format($product['rating_avg'], 1, ',', '') ?>
                </span>
            <?php endif; ?>
        </div>

        <h3 class="product-card__title">
            <a href="<?= e($href) ?>"><?= e($product['name']) ?></a>
        </h3>

        <?php if ($product['category_type'] === 'liquid' && $product['variants_count'] > 1): ?>
            <p class="product-card__variants">0,5 л · 1 л · 5 л</p>
        <?php endif; ?>

        <div class="product-card__footer">
            <?= price($product['price'], $product['old_price'], 'md', $product['variants_count'] > 1) ?>

            <?php if ($inStock): ?>
                <a class="btn btn--icon btn--primary product-card__cta" href="<?= e($href) ?>" aria-label="<?= $isMachine ? 'Настроить аппарат' : 'Выбрать' ?>: <?= e($product['name']) ?>">
                    <?= icon($isMachine ? 'cog' : 'arrow-right') ?>
                </a>
            <?php else: ?>
                <span class="product-card__stock">Нет в наличии</span>
            <?php endif; ?>
        </div>
    </div>
</article>
