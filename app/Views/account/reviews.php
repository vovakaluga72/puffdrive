<?php
/**
 * Личный кабинет — мои отзывы со статусом модерации.
 *
 * @var array $user
 * @var array $stats
 * @var array $reviews
 */

use App\Core\View;
use App\Models\Review;

$tones = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'muted'];
?>
<section class="account">
    <div class="container">
        <?= View::partial('account/_nav', ['user' => $user, 'stats' => $stats, 'section' => 'reviews']) ?>

        <div class="panel">
            <div class="panel__head">
                <h1 class="panel__title">Мои отзывы</h1>
            </div>

            <?php if ($reviews): ?>
                <ul class="my-reviews">
                    <?php foreach ($reviews as $review): ?>
                        <li class="my-review">
                            <div class="my-review__head">
                                <?php if ((int) $review['product_active'] === 1): ?>
                                    <a class="my-review__product" href="<?= e(url('/product/' . $review['product_slug'])) ?>"><?= e($review['product_name']) ?></a>
                                <?php else: ?>
                                    <span class="my-review__product"><?= e($review['product_name']) ?></span>
                                <?php endif; ?>
                                <span class="status status--<?= e($tones[$review['status']] ?? 'muted') ?>">
                                    <?= e(Review::STATUSES[$review['status']] ?? $review['status']) ?>
                                </span>
                            </div>
                            <div class="my-review__rating">
                                <?= rating_stars((float) $review['rating']) ?>
                                <span class="my-review__date"><?= e(format_date($review['created_at'])) ?></span>
                                <?php if ((int) $review['is_verified_purchase'] === 1): ?>
                                    <span class="badge badge--verified"><?= icon('check') ?> Купил товар</span>
                                <?php endif; ?>
                            </div>
                            <p class="my-review__text"><?= nl2br(e($review['text'])) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="empty-state">
                    <span class="empty-state__icon"><?= icon('star') ?></span>
                    <h2 class="empty-state__title">Вы ещё не оставляли отзывов</h2>
                    <p class="empty-state__text">
                        Поделитесь впечатлениями о купленных ароматах на странице товара — это помогает другим покупателям.
                        Отзывы публикуются после проверки модератором.
                    </p>
                    <a class="btn btn--outline" href="<?= e(url('/catalog')) ?>">Перейти в каталог</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
