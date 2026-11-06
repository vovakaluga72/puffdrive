<?php
/**
 * Пагинация: «‹ 1 … 4 5 [6] 7 8 … 20 ›».
 *
 * @var int      $page
 * @var int      $pages
 * @var callable $link  fn(int $page): string — адрес страницы
 */
if ($pages <= 1) {
    return;
}

$window = 1;
$numbers = [1, $pages];
for ($i = $page - $window; $i <= $page + $window; $i++) {
    if ($i > 1 && $i < $pages) {
        $numbers[] = $i;
    }
}
$numbers = array_unique($numbers);
sort($numbers);
?>
<nav class="pagination" aria-label="Страницы">
    <?php if ($page > 1): ?>
        <a class="pagination__link pagination__link--arrow" href="<?= e($link($page - 1)) ?>" rel="prev" aria-label="Предыдущая страница" data-ajax-link>
            <?= icon('chevron-left') ?>
        </a>
    <?php else: ?>
        <span class="pagination__link pagination__link--arrow is-disabled" aria-hidden="true"><?= icon('chevron-left') ?></span>
    <?php endif; ?>

    <?php $prev = 0; foreach ($numbers as $number): ?>
        <?php if ($number - $prev > 1): ?>
            <span class="pagination__gap" aria-hidden="true">…</span>
        <?php endif; ?>
        <?php if ($number === $page): ?>
            <span class="pagination__link is-current" aria-current="page"><?= $number ?></span>
        <?php else: ?>
            <a class="pagination__link" href="<?= e($link($number)) ?>" data-ajax-link aria-label="Страница <?= $number ?>"><?= $number ?></a>
        <?php endif; ?>
    <?php $prev = $number; endforeach; ?>

    <?php if ($page < $pages): ?>
        <a class="pagination__link pagination__link--arrow" href="<?= e($link($page + 1)) ?>" rel="next" aria-label="Следующая страница" data-ajax-link>
            <?= icon('chevron-right') ?>
        </a>
    <?php else: ?>
        <span class="pagination__link pagination__link--arrow is-disabled" aria-hidden="true"><?= icon('chevron-right') ?></span>
    <?php endif; ?>
</nav>
