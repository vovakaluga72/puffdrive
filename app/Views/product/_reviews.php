<?php
/**
 * Отзывы на странице товара: средняя оценка, распределение, список.
 * Форма отправки отзыва добавляется на этапе 5.
 *
 * @var array $product
 * @var array $reviews
 * @var array $distribution [5 => n, …, 1 => n]
 */
$count = $product['rating_count'];
$max = max(1, max($distribution));
?>
<section class="product-reviews" id="reviews" aria-labelledby="reviews-title">
    <header class="section-head">
        <h2 class="section-title" id="reviews-title">Отзывы<?= $count ? ' <span class="section-title__count">' . $count . '</span>' : '' ?></h2>
    </header>

    <div class="product-reviews__layout">
        <div class="rating-summary">
            <?php if ($count > 0): ?>
                <p class="rating-summary__value"><?= number_format($product['rating_avg'], 1, ',', '') ?></p>
                <?= rating_stars($product['rating_avg'], 'stars--lg') ?>
                <p class="rating-summary__count"><?= $count ?> <?= plural($count, ['оценка', 'оценки', 'оценок']) ?></p>
                <ul class="rating-bars">
                    <?php foreach ($distribution as $stars => $n): ?>
                        <li class="rating-bars__row">
                            <span class="rating-bars__label"><?= $stars ?> ★</span>
                            <span class="rating-bars__track"><span style="width: <?= round($n / $max * 100) ?>%"></span></span>
                            <span class="rating-bars__count"><?= $n ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="rating-summary__empty">У этого товара пока нет отзывов. Купили его? Расскажите, как он вам!</p>
            <?php endif; ?>
        </div>

        <?php if ($reviews): ?>
            <ul class="review-list">
                <?php foreach ($reviews as $review): ?>
                    <li class="review">
                        <div class="review__head">
                            <span class="review__avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($review['author'], 0, 1))) ?></span>
                            <div>
                                <strong class="review__author"><?= e($review['author']) ?></strong>
                                <span class="review__date"><?= e(format_date($review['created_at'])) ?></span>
                            </div>
                            <?= rating_stars((float) $review['rating']) ?>
                        </div>
                        <?php if ((int) $review['is_verified_purchase'] === 1): ?>
                            <span class="badge badge--verified"><?= icon('check') ?> Купил товар</span>
                        <?php endif; ?>
                        <p class="review__text"><?= nl2br(e($review['text'])) ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
