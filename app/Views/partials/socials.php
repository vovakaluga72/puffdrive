<?php
/**
 * Ряд иконок соцсетей и мессенджеров (ссылки — в настройках магазина).
 *
 * @var string|null $class
 */
$socials = App\Models\Setting::socials();
if (!$socials) {
    return;
}
?>
<ul class="socials <?= e($class ?? '') ?>">
    <?php foreach ($socials as $social): ?>
        <li>
            <a class="socials__link socials__link--<?= e($social['key']) ?>" href="<?= e($social['url']) ?>"
               target="_blank" rel="noopener noreferrer" aria-label="<?= e($social['title']) ?>" title="<?= e($social['title']) ?>">
                <?= icon($social['key']) ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>
