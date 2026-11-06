<?php
/**
 * Логотип Puff Drive: знак-«капля тумана» + словесный знак.
 *
 * @var string|null $size     sm | md | lg | xl
 * @var bool|null   $tagline  показывать подпись «Сухой туман»
 */
$size = $size ?? 'md';
$tagline = $tagline ?? true;
$gid = 'logoGold-' . bin2hex(random_bytes(3)); // уникальный id градиента, если логотипов на странице несколько
?>
<span class="logo logo--<?= e($size) ?>">
    <svg class="logo__mark" viewBox="0 0 48 48" aria-hidden="true" focusable="false">
        <defs>
            <linearGradient id="<?= $gid ?>" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#F3DFB0"/>
                <stop offset=".5" stop-color="#C9A96E"/>
                <stop offset="1" stop-color="#8E6F3E"/>
            </linearGradient>
        </defs>
        <circle cx="24" cy="24" r="22.5" fill="none" stroke="url(#<?= $gid ?>)" stroke-width="1.2" opacity=".7"/>
        <path d="M24 9.5c-5.6 6.8-9.4 12.4-9.4 17.2a9.4 9.4 0 0 0 18.8 0c0-4.8-3.8-10.4-9.4-17.2z" fill="none" stroke="url(#<?= $gid ?>)" stroke-width="1.8" stroke-linejoin="round"/>
        <path d="M18.6 28.4c1.6-1.4 3.4-1.4 5.4 0s3.8 1.4 5.4 0M18.6 32.4c1.6-1.4 3.4-1.4 5.4 0s3.8 1.4 5.4 0" fill="none" stroke="url(#<?= $gid ?>)" stroke-width="1.5" stroke-linecap="round"/>
    </svg>
    <span class="logo__text">
        <span class="logo__name">Puff<span class="logo__name-accent">Drive</span></span>
        <?php if ($tagline): ?>
            <span class="logo__tagline"><?= e(setting('shop_tagline', 'Сухой туман')) ?></span>
        <?php endif; ?>
    </span>
</span>
