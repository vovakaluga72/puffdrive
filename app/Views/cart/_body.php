<?php
/**
 * Содержимое корзины: позиции + итоги + промокод.
 *
 * @var array $items
 * @var array $summary
 */

use App\Services\PromoService;

if (!$items): ?>
    <div class="empty-state cart-empty">
        <span class="empty-state__icon"><?= icon('cart') ?></span>
        <h2 class="empty-state__title">В корзине пока пусто</h2>
        <p class="empty-state__text">Загляните в каталог — там ароматы для салона, аппараты и всё для их обслуживания.</p>
        <div class="form__actions">
            <a class="btn btn--primary" href="<?= e(url('/catalog')) ?>">Перейти в каталог <?= icon('arrow-right') ?></a>
        </div>
    </div>
    <?php return; ?>
<?php endif; ?>

<div class="cart-layout">
    <div class="cart-lines">
        <?php if ($summary['has_unavailable']): ?>
            <div class="alert alert--error">
                <?= icon('alert') ?>
                <span>Некоторых товаров уже нет в наличии — они не войдут в заказ. Удалите их из корзины.</span>
            </div>
        <?php endif; ?>

        <ul class="cart-list">
            <?php foreach ($items as $item):
                $href = url('/product/' . $item['slug']); ?>
                <li class="cart-line<?= $item['available'] ? '' : ' is-unavailable' ?>" data-line="<?= e($item['key']) ?>">
                    <a class="cart-line__media" href="<?= e($href) ?>" tabindex="-1" aria-hidden="true">
                        <?php if ($item['image']): ?>
                            <img src="<?= e(upload_url($item['image'])) ?>" alt="" loading="lazy">
                        <?php else: ?>
                            <?= placeholder_svg($item['category_type'], $item['aroma_color']) ?>
                        <?php endif; ?>
                    </a>

                    <div class="cart-line__info">
                        <a class="cart-line__name" href="<?= e($href) ?>"><?= e($item['name']) ?></a>
                        <span class="cart-line__variant"><?= e($item['label']) ?> · <?= e($item['sku']) ?></span>

                        <?php if ($item['config']): ?>
                            <ul class="cart-config" aria-label="Конфигурация аппарата">
                                <?php foreach ($item['config'] as $slot): ?>
                                    <li><span class="aroma-dot" style="--dot: <?= e($slot['color']) ?>"></span> Слот <?= (int) $slot['slot'] ?> — <?= e($slot['name']) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <?php if (!$item['available']): ?>
                            <span class="cart-line__warn"><?= icon('alert') ?> Нет в наличии</span>
                        <?php elseif ($item['qty_exceeds']): ?>
                            <span class="cart-line__warn"><?= icon('alert') ?> Доступно только <?= (int) $item['stock'] ?> шт.</span>
                        <?php endif; ?>

                        <span class="cart-line__unit"><?= price($item['price'], $item['old_price'], 'sm') ?> <span class="text-muted">за шт.</span></span>
                    </div>

                    <?php if ($item['available']): ?>
                        <form class="qty qty--sm cart-line__qty" action="<?= e(url('/cart/update')) ?>" method="post" data-cart-qty>
                            <?= csrf_field() ?>
                            <input type="hidden" name="key" value="<?= e($item['key']) ?>">
                            <!-- Невидимая кнопка по умолчанию: Enter в поле количества не должен нажимать «−» -->
                            <button class="visually-hidden" type="submit" tabindex="-1">Обновить</button>
                            <button class="qty__btn" type="submit" name="delta" value="-1" aria-label="Уменьшить количество"<?= $item['quantity'] <= 1 ? ' disabled' : '' ?>><?= icon('minus') ?></button>
                            <label class="visually-hidden" for="qty-<?= e(md5($item['key'])) ?>">Количество</label>
                            <input class="qty__input" id="qty-<?= e(md5($item['key'])) ?>" type="number" name="quantity"
                                   value="<?= (int) $item['quantity'] ?>" min="1" max="<?= (int) $item['max_qty'] ?>" inputmode="numeric">
                            <button class="qty__btn" type="submit" name="delta" value="1" aria-label="Увеличить количество"<?= $item['quantity'] >= $item['max_qty'] ? ' disabled' : '' ?>><?= icon('plus') ?></button>
                        </form>
                    <?php else: ?>
                        <span class="cart-line__qty cart-line__qty--off"><?= (int) $item['quantity'] ?> шт.</span>
                    <?php endif; ?>

                    <div class="cart-line__total">
                        <?= price($item['line_total'], $item['line_regular'] > $item['line_total'] ? $item['line_regular'] : null, 'md') ?>
                    </div>

                    <form class="cart-line__remove" action="<?= e(url('/cart/remove')) ?>" method="post" data-cart-remove>
                        <?= csrf_field() ?>
                        <input type="hidden" name="key" value="<?= e($item['key']) ?>">
                        <button class="icon-btn icon-btn--danger" type="submit" aria-label="Удалить «<?= e($item['name']) ?>» из корзины"><?= icon('trash') ?></button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>

        <a class="link-arrow cart-continue" href="<?= e(url('/catalog')) ?>"><?= icon('chevron-left') ?> Продолжить покупки</a>
    </div>

    <aside class="cart-summary" aria-label="Итого">
        <h2 class="cart-summary__title">Ваш заказ</h2>

        <!-- Промокод -->
        <?php $promo = $summary['promo']; ?>
        <?php if ($promo): ?>
            <div class="promo-applied<?= $summary['promo_error'] ? ' is-invalid' : '' ?>">
                <span class="promo-applied__code"><?= icon('tag') ?> <?= e($promo['code']) ?></span>
                <span class="promo-applied__value"><?= e(PromoService::label($promo)) ?></span>
                <form action="<?= e(url('/cart/promo/remove')) ?>" method="post" data-cart-action>
                    <?= csrf_field() ?>
                    <button class="icon-btn icon-btn--danger" type="submit" aria-label="Удалить промокод"><?= icon('close') ?></button>
                </form>
                <?php if ($summary['promo_error']): ?>
                    <p class="promo-applied__error"><?= e($summary['promo_error']) ?></p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <form class="promo-form" action="<?= e(url('/cart/promo')) ?>" method="post" data-cart-action>
                <?= csrf_field() ?>
                <label class="visually-hidden" for="promo-code">Промокод</label>
                <input class="input input--sm" id="promo-code" type="text" name="code" placeholder="Промокод" maxlength="40"
                       autocomplete="off" autocapitalize="characters" spellcheck="false">
                <button class="btn btn--outline btn--sm" type="submit">Применить</button>
            </form>
        <?php endif; ?>

        <dl class="summary-rows">
            <div class="summary-row">
                <dt>Товары, <?= (int) $summary['units'] ?> шт.</dt>
                <dd><?= price_tag($summary['subtotal'] + $summary['savings'], 'price--inline') ?></dd>
            </div>
            <?php if ($summary['savings'] > 0): ?>
                <div class="summary-row summary-row--accent">
                    <dt>Скидка по акциям</dt>
                    <dd>−<?= price_tag($summary['savings'], 'price--inline') ?></dd>
                </div>
            <?php endif; ?>
            <?php if ($summary['discount'] > 0): ?>
                <div class="summary-row summary-row--accent">
                    <dt>Промокод <?= e($promo['code']) ?></dt>
                    <dd>−<?= price_tag($summary['discount'], 'price--inline') ?></dd>
                </div>
            <?php endif; ?>
            <div class="summary-row">
                <dt>Доставка</dt>
                <dd class="text-muted">при оформлении</dd>
            </div>
        </dl>

        <?php if ($summary['free_threshold'] > 0): ?>
            <div class="free-delivery">
                <?php if ($summary['to_free'] > 0): ?>
                    <p>До бесплатной доставки: <strong><?= price_tag($summary['to_free'], 'price--inline') ?></strong></p>
                <?php else: ?>
                    <p><?= icon('check') ?> Доставка бесплатная</p>
                <?php endif; ?>
                <div class="free-delivery__bar"><span style="width: <?= min(100, round(($summary['total'] / $summary['free_threshold']) * 100)) ?>%"></span></div>
            </div>
        <?php endif; ?>

        <div class="summary-total">
            <span>Итого</span>
            <?= price($summary['total'], null, 'lg') ?>
        </div>

        <?php if ($summary['units'] > 0): ?>
            <a class="btn btn--primary btn--lg btn--block" href="<?= e(url('/checkout')) ?>">Оформить заказ <?= icon('arrow-right') ?></a>
        <?php else: ?>
            <span class="btn btn--primary btn--lg btn--block is-disabled" aria-disabled="true">Нет товаров для заказа</span>
        <?php endif; ?>
        <p class="cart-summary__note"><?= icon('shield') ?> Можно оформить без регистрации</p>
    </aside>
</div>
