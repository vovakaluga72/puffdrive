<?php
/**
 * Главная страница.
 *
 * @var bool  $showSplash
 * @var array $splashLines
 * @var array $categories
 * @var array $popular
 * @var array $reviews
 * @var int   $aromaCount
 * @var array $heroSlots   6 популярных ароматов для демо-панели аппарата
 */

use App\Core\View;

$categoryMeta = [
    'liquid'  => ['icon' => 'droplet', 'hint' => '0,5 · 1 · 5 литров'],
    'machine' => ['icon' => 'machine', 'hint' => 'До 6 ароматов на панели'],
    'part'    => ['icon' => 'cog',     'hint' => 'Запчасти и сервис'],
];
?>

<?php if ($showSplash): ?>
    <?= View::partial('partials/splash', ['splashLines' => $splashLines]) ?>
<?php endif; ?>

<!-- ============================ HERO ============================ -->
<section class="hero" aria-labelledby="hero-title">
    <canvas class="hero__fog" data-fog data-fog-density="0.8" aria-hidden="true"></canvas>
    <div class="hero__glow" aria-hidden="true"></div>

    <div class="container hero__inner">
        <div class="hero__content">
            <span class="eyebrow reveal">Сухой туман · Гродно · доставка по Беларуси</span>
            <h1 class="hero__title reveal" id="hero-title">
                Сухой туман<br><em>премиум-класса</em><br>для салона и бизнеса
            </h1>
            <p class="hero__lead reveal">
                Жидкости 0,5, 1 и 5 литров, аппараты самообслуживания с настройкой до шести ароматов
                и комплектующие — всё для ароматизации авто в одном магазине.
            </p>
            <div class="hero__actions reveal">
                <a class="btn btn--primary btn--lg" href="<?= e(url('/catalog')) ?>">
                    Перейти в каталог <?= icon('arrow-right') ?>
                </a>
                <a class="btn btn--ghost btn--lg" href="<?= e(url('/business')) ?>">
                    Хочу свой аппарат
                </a>
            </div>
            <dl class="hero__stats reveal">
                <div class="hero__stat"><dt><?= (int) $aromaCount ?></dt><dd><?= plural((int) $aromaCount, ['аромат', 'аромата', 'ароматов']) ?> в каталоге</dd></div>
                <div class="hero__stat"><dt>3</dt><dd>объёма: 0,5 / 1 / 5 л</dd></div>
                <div class="hero__stat"><dt>до 9</dt><dd>кнопок на панели аппарата</dd></div>
            </dl>
        </div>

        <div class="hero__visual reveal" aria-hidden="true">
            <div class="hero__machine-wrap">
                <?= View::partial('partials/machine-panel', ['slots' => $heroSlots, 'class' => 'machine--hero machine--demo']) ?>
                <div class="hero__mist" aria-hidden="true"></div>
            </div>
        </div>
    </div>

    <a class="hero__scroll" href="#categories" aria-label="Листать вниз">
        <span></span>
    </a>
</section>

<!-- ========================= КАТЕГОРИИ ========================= -->
<section class="section" id="categories" aria-labelledby="categories-title">
    <div class="container">
        <header class="section-head reveal">
            <div>
                <span class="eyebrow">Каталог</span>
                <h2 class="section-title" id="categories-title">Всё для сухого тумана</h2>
            </div>
            <p class="section-head__text">Выберите, с чего начать: жидкости для заправки, аппарат под ключ или запчасти для обслуживания.</p>
        </header>

        <div class="category-grid">
            <?php foreach ($categories as $i => $category):
                $meta = $categoryMeta[$category['type']] ?? ['icon' => 'sparkles', 'hint' => '']; ?>
                <a class="category-card category-card--<?= e($category['type']) ?> reveal" style="--delay: <?= $i * 90 ?>ms"
                   href="<?= e(url('/catalog/' . $category['slug'])) ?>">
                    <span class="category-card__icon"><?= icon($meta['icon']) ?></span>
                    <span class="category-card__hint"><?= e($meta['hint']) ?></span>
                    <h3 class="category-card__title"><?= e($category['name']) ?></h3>
                    <p class="category-card__text"><?= e(str_limit((string) $category['description'], 110)) ?></p>
                    <span class="category-card__footer">
                        <span class="category-card__count">
                            <?= (int) $category['products_count'] ?> <?= plural((int) $category['products_count'], ['товар', 'товара', 'товаров']) ?>
                            <?php if ($category['min_price'] !== null): ?>
                                · <span class="category-card__from">от <?= price_tag((float) $category['min_price'], 'price--inline') ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="category-card__arrow"><?= icon('arrow-up-right') ?></span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ======================== ПОПУЛЯРНОЕ ======================== -->
<?php if ($popular): ?>
<section class="section section--tight" aria-labelledby="popular-title">
    <div class="container">
        <header class="section-head reveal">
            <div>
                <span class="eyebrow">Выбор покупателей</span>
                <h2 class="section-title" id="popular-title">Популярные товары</h2>
            </div>
            <a class="link-arrow" href="<?= e(url('/catalog', ['sort' => 'popular'])) ?>">Весь каталог <?= icon('arrow-right') ?></a>
        </header>

        <div class="product-grid">
            <?php foreach ($popular as $product): ?>
                <?= View::partial('partials/product-card', ['product' => $product]) ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ===================== ДЛЯ ПРЕДПРИНИМАТЕЛЕЙ ===================== -->
<section class="section business" aria-labelledby="business-title">
    <div class="business__bg" aria-hidden="true"></div>
    <div class="container business__inner">
        <div class="business__content reveal">
            <span class="eyebrow">Для предпринимателей</span>
            <h2 class="section-title" id="business-title">Зарабатывайте<br>на&nbsp;<em>сухом тумане</em></h2>
            <p class="business__lead">
                Аппарат самообслуживания работает без продавца: клиент выбирает аромат, оплачивает наличными
                или по QR-коду — и через минуту салон пахнет как новый. Вы только пополняете жидкости.
            </p>

            <ul class="business__figures">
                <li><strong>2–3&nbsp;BYN</strong><span>себестоимость одной услуги</span></li>
                <li><strong>15–20&nbsp;BYN</strong><span>цена услуги для клиента</span></li>
                <li><strong>от 3&nbsp;мес.</strong><span>срок окупаемости*</span></li>
            </ul>
            <p class="business__note">* Пример расчёта. Фактические показатели зависят от проходимости точки и выбранной модели.</p>

            <div class="business__actions">
                <a class="btn btn--primary btn--lg" href="<?= e(url('/business')) ?>">Оставить заявку <?= icon('arrow-right') ?></a>
                <a class="btn btn--ghost btn--lg" href="<?= e(url('/catalog/apparaty')) ?>">Смотреть аппараты</a>
            </div>
        </div>

        <ol class="business__steps">
            <li class="business-step reveal" style="--delay: 0ms">
                <span class="business-step__num">01</span>
                <h3>Выбираете аппарат</h3>
                <p>Classic, Pro или Black Edition — под вашу точку и бюджет.</p>
            </li>
            <li class="business-step reveal" style="--delay: 90ms">
                <span class="business-step__num">02</span>
                <h3>Настраиваете ароматы</h3>
                <p>В конфигураторе собираете свою линейку: Морской бриз, Чёрный лёд, Антитабак…</p>
            </li>
            <li class="business-step reveal" style="--delay: 180ms">
                <span class="business-step__num">03</span>
                <h3>Устанавливаете на точке</h3>
                <p>Автомойка, АЗС, парковка ТЦ. Поможем с монтажом и обучением.</p>
            </li>
            <li class="business-step reveal" style="--delay: 270ms">
                <span class="business-step__num">04</span>
                <h3>Получаете доход</h3>
                <p>Пополняете жидкости по оптовой цене — мы всегда рядом.</p>
            </li>
        </ol>
    </div>
</section>

<!-- ======================== ПРЕИМУЩЕСТВА ======================== -->
<section class="section section--tight" aria-labelledby="advantages-title">
    <div class="container">
        <header class="section-head section-head--center reveal">
            <span class="eyebrow">Почему Puff Drive</span>
            <h2 class="section-title" id="advantages-title">Сервис, которому доверяют</h2>
        </header>

        <ul class="advantages">
            <li class="advantage reveal" style="--delay: 0ms">
                <span class="advantage__icon"><?= icon('sparkles') ?></span>
                <h3>Стойкие ароматы</h3>
                <p>Жидкости созданы для сухого тумана: держатся до 4–6 недель и не оставляют следов.</p>
            </li>
            <li class="advantage reveal" style="--delay: 90ms">
                <span class="advantage__icon"><?= icon('truck') ?></span>
                <h3>Доставка по Беларуси</h3>
                <p>Курьер по Гродно, Европочта и Белпочта. Бесплатно от <?= e(number_format((float) setting('free_delivery_threshold', '150'), 0, '', ' ')) ?>&nbsp;BYN.</p>
            </li>
            <li class="advantage reveal" style="--delay: 180ms">
                <span class="advantage__icon"><?= icon('shield') ?></span>
                <h3>Гарантия и сервис</h3>
                <p>Гарантия на аппараты 12 месяцев, запчасти в наличии на складе в Гродно.</p>
            </li>
            <li class="advantage reveal" style="--delay: 270ms">
                <span class="advantage__icon"><?= icon('headset') ?></span>
                <h3>Поддержка владельцев</h3>
                <p>Поможем подобрать ароматы, настроить аппарат и рассчитать окупаемость.</p>
            </li>
        </ul>
    </div>
</section>

<!-- =========================== ОТЗЫВЫ =========================== -->
<?php if ($reviews): ?>
<section class="section section--tight" aria-labelledby="reviews-title">
    <div class="container">
        <header class="section-head reveal">
            <div>
                <span class="eyebrow">Отзывы</span>
                <h2 class="section-title" id="reviews-title">Говорят покупатели</h2>
            </div>
            <div class="slider-nav" data-slider-nav="reviews">
                <button class="icon-btn icon-btn--outline" type="button" aria-label="Предыдущие отзывы" data-slider-prev><?= icon('chevron-left') ?></button>
                <button class="icon-btn icon-btn--outline" type="button" aria-label="Следующие отзывы" data-slider-next><?= icon('chevron-right') ?></button>
            </div>
        </header>

        <div class="reviews-track" data-slider="reviews" tabindex="0" aria-label="Отзывы покупателей">
            <?php foreach ($reviews as $review): ?>
                <figure class="review-card reveal">
                    <div class="review-card__head">
                        <?= rating_stars((float) $review['rating']) ?>
                        <?php if ((int) $review['is_verified_purchase'] === 1): ?>
                            <span class="badge badge--verified"><?= icon('check') ?> Купил товар</span>
                        <?php endif; ?>
                    </div>
                    <blockquote class="review-card__text"><?= e($review['text']) ?></blockquote>
                    <figcaption class="review-card__author">
                        <span class="review-card__avatar" aria-hidden="true"><?= e(mb_substr($review['author'], 0, 1)) ?></span>
                        <span>
                            <strong><?= e($review['author']) ?></strong>
                            <a href="<?= e(url('/product/' . $review['product_slug'])) ?>"><?= e($review['product_name']) ?></a>
                        </span>
                    </figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================ CTA ============================ -->
<section class="section section--tight" aria-labelledby="cta-title">
    <div class="container">
        <div class="cta reveal">
            <canvas class="cta__fog" data-fog data-fog-density="0.6" aria-hidden="true"></canvas>
            <div class="cta__content">
                <h2 class="cta__title" id="cta-title">Готовы наполнить салон <em>ароматом</em>?</h2>
                <p class="cta__text">Подберём жидкости под ваш аппарат или соберём аппарат под ваш бизнес.</p>
                <div class="cta__actions">
                    <a class="btn btn--primary btn--lg" href="<?= e(url('/catalog')) ?>">В каталог</a>
                    <a class="btn btn--ghost btn--lg" href="<?= e(tel_href(setting('phone'))) ?>"><?= icon('phone') ?> <?= e(format_phone(setting('phone'))) ?></a>
                </div>
            </div>
        </div>
    </div>
</section>
