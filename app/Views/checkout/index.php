<?php
/**
 * Оформление заказа.
 *
 * @var array      $items       доступные позиции корзины
 * @var array      $summary
 * @var array      $deliveries  DeliveryService::methods()
 * @var array      $payments    PaymentService::methods()
 * @var array|null $user
 * @var array      $addresses
 * @var array      $prefill
 */

use App\Core\View;
use App\Models\Address;

$selectedDelivery = (string) old('delivery_method', 'pickup');
$selectedDelivery = isset($deliveries[$selectedDelivery]) ? $selectedDelivery : 'pickup';
$selectedPayment  = (string) old('payment_method', 'cod');
$afterDiscount    = $summary['total'];
$threshold        = $summary['free_threshold'];

$deliveryCost = static function (array $method) use ($afterDiscount, $threshold): float {
    if ($method['free'] || ($threshold > 0 && $afterDiscount >= $threshold)) {
        return 0.0;
    }
    return $method['cost'];
};
$currentDeliveryCost = $deliveryCost($deliveries[$selectedDelivery]);
?>
<section class="checkout-page">
    <div class="container">
        <?= View::partial('partials/breadcrumbs', ['items' => [
            ['title' => 'Корзина', 'url' => url('/cart')],
            ['title' => 'Оформление заказа'],
        ]]) ?>
        <h1 class="page-title">Оформление заказа</h1>

        <form class="checkout" action="<?= e(url('/checkout')) ?>" method="post" novalidate data-validate data-checkout
              data-subtotal="<?= e($afterDiscount) ?>" data-threshold="<?= e($threshold) ?>">
            <?= csrf_field() ?>

            <div class="checkout__main">
                <!-- 1. Контакты -->
                <section class="checkout-step" aria-labelledby="step-contacts">
                    <h2 class="checkout-step__title" id="step-contacts"><span>1</span> Контактные данные</h2>
                    <?php if (!$user): ?>
                        <p class="checkout-step__hint">
                            Есть аккаунт? <a href="<?= e(url('/login')) ?>">Войдите</a> — данные подставятся автоматически,
                            а заказ появится в истории. Можно продолжить и без входа.
                        </p>
                    <?php endif; ?>

                    <?= View::partial('partials/form-field', [
                        'name' => 'name', 'label' => 'Имя и фамилия', 'required' => true, 'value' => $prefill['name'],
                        'autocomplete' => 'name', 'minlength' => 2, 'maxlength' => 100,
                    ]) ?>
                    <div class="form__grid">
                        <?= View::partial('partials/form-field', [
                            'name' => 'phone', 'label' => 'Телефон', 'type' => 'tel', 'required' => true, 'value' => $prefill['phone'],
                            'autocomplete' => 'tel', 'inputmode' => 'tel', 'placeholder' => '+375 (29) 123-45-67',
                            'data' => ['phone' => ''],
                        ]) ?>
                        <?= View::partial('partials/form-field', [
                            'name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'value' => $prefill['email'],
                            'autocomplete' => 'email', 'maxlength' => 190, 'hint' => 'Пришлём подтверждение и ссылку на заказ',
                        ]) ?>
                    </div>
                </section>

                <!-- 2. Доставка -->
                <section class="checkout-step" aria-labelledby="step-delivery">
                    <h2 class="checkout-step__title" id="step-delivery"><span>2</span> Доставка</h2>

                    <div class="choice-grid" role="radiogroup" aria-label="Способ доставки">
                        <?php foreach ($deliveries as $code => $method):
                            $cost = $deliveryCost($method); ?>
                            <label class="choice">
                                <input type="radio" name="delivery_method" value="<?= e($code) ?>"
                                       data-cost="<?= e($method['cost']) ?>" data-free="<?= $method['free'] ? '1' : '0' ?>"
                                       data-fields="<?= e(implode(',', $method['fields'])) ?>"
                                       <?= $code === $selectedDelivery ? 'checked' : '' ?>>
                                <span class="choice__body">
                                    <span class="choice__icon"><?= icon($method['icon']) ?></span>
                                    <span class="choice__text">
                                        <span class="choice__title"><?= e($method['title']) ?></span>
                                        <span class="choice__desc"><?= e($method['description']) ?></span>
                                        <span class="choice__eta"><?= e($method['eta']) ?></span>
                                    </span>
                                    <span class="choice__price" data-delivery-price="<?= e($code) ?>">
                                        <?= $cost > 0 ? price_tag($cost, 'price--inline') : 'Бесплатно' ?>
                                    </span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if (error('delivery_method')): ?>
                        <p class="field__error"><?= e(error('delivery_method')) ?></p>
                    <?php endif; ?>

                    <div class="delivery-fields" data-delivery-fields>
                        <?php if ($addresses): ?>
                            <div class="field" data-saved-addresses>
                                <label class="field__label" for="saved-address">Сохранённый адрес</label>
                                <select class="select" id="saved-address" data-saved-address>
                                    <option value="">— выбрать —</option>
                                    <?php foreach ($addresses as $address): ?>
                                        <option value="<?= (int) $address['id'] ?>"
                                                data-city="<?= e($address['city']) ?>" data-address="<?= e($address['address']) ?>"
                                                data-postcode="<?= e((string) $address['postcode']) ?>">
                                            <?= e($address['title']) ?>: <?= e(Address::format($address)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="form__grid">
                            <div data-field="city">
                                <?= View::partial('partials/form-field', [
                                    'name' => 'city', 'label' => 'Город', 'value' => $prefill['city'], 'required' => true,
                                    'autocomplete' => 'address-level2', 'maxlength' => 100, 'placeholder' => 'Гродно',
                                ]) ?>
                            </div>
                            <div data-field="postcode">
                                <?= View::partial('partials/form-field', [
                                    'name' => 'postcode', 'label' => 'Индекс', 'value' => $prefill['postcode'], 'required' => true,
                                    'autocomplete' => 'postal-code', 'inputmode' => 'numeric', 'maxlength' => 6, 'pattern' => '\d{6}',
                                    'placeholder' => '230025',
                                ]) ?>
                            </div>
                        </div>
                        <div data-field="address">
                            <?= View::partial('partials/form-field', [
                                'name' => 'address', 'label' => 'Улица, дом, квартира', 'value' => $prefill['address'], 'required' => true,
                                'autocomplete' => 'street-address', 'minlength' => 5, 'maxlength' => 255,
                                'placeholder' => 'ул. Советская, д. 10, кв. 5',
                            ]) ?>
                        </div>
                        <div data-field="point">
                            <?= View::partial('partials/form-field', [
                                'name' => 'point', 'label' => 'Отделение Европочты', 'required' => true, 'maxlength' => 255,
                                'placeholder' => 'Например: № 12, ул. Горького, 91',
                                'hint' => 'Номер или адрес отделения — список на сайте evropochta.by',
                            ]) ?>
                        </div>
                        <p class="delivery-fields__note" data-field="pickup-note">
                            <?= icon('info') ?> Менеджер позвонит, когда заказ будет готов к выдаче.
                        </p>
                    </div>
                </section>

                <!-- 3. Оплата -->
                <section class="checkout-step" aria-labelledby="step-payment">
                    <h2 class="checkout-step__title" id="step-payment"><span>3</span> Оплата</h2>
                    <div class="choice-grid" role="radiogroup" aria-label="Способ оплаты">
                        <?php foreach ($payments as $code => $method): ?>
                            <label class="choice<?= $method['enabled'] ? '' : ' is-disabled' ?>">
                                <input type="radio" name="payment_method" value="<?= e($code) ?>"
                                       <?= $method['enabled'] ? '' : 'disabled' ?>
                                       <?= $code === $selectedPayment && $method['enabled'] ? 'checked' : '' ?>>
                                <span class="choice__body">
                                    <span class="choice__icon"><?= icon($method['icon']) ?></span>
                                    <span class="choice__text">
                                        <span class="choice__title"><?= e($method['title']) ?></span>
                                        <span class="choice__desc"><?= e($method['description']) ?></span>
                                    </span>
                                    <?php if (!$method['enabled']): ?>
                                        <span class="badge">скоро</span>
                                    <?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if (error('payment_method')): ?>
                        <p class="field__error"><?= e(error('payment_method')) ?></p>
                    <?php endif; ?>
                </section>

                <!-- 4. Комментарий -->
                <section class="checkout-step" aria-labelledby="step-comment">
                    <h2 class="checkout-step__title" id="step-comment"><span>4</span> Комментарий к заказу</h2>
                    <?= View::partial('partials/form-field', [
                        'name' => 'comment', 'label' => 'Комментарий (необязательно)', 'type' => 'textarea', 'maxlength' => 1000,
                        'placeholder' => 'Удобное время звонка, пожелания по доставке…',
                    ]) ?>
                </section>
            </div>

            <!-- Итоги -->
            <aside class="checkout__aside">
                <div class="cart-summary checkout-summary">
                    <h2 class="cart-summary__title">Ваш заказ</h2>
                    <ul class="mini-lines">
                        <?php foreach ($items as $item): ?>
                            <li class="mini-line">
                                <span class="mini-line__name">
                                    <?= e($item['name']) ?>
                                    <span class="mini-line__meta"><?= e($item['label']) ?> × <?= (int) $item['quantity'] ?></span>
                                    <?php if ($item['config']): ?>
                                        <span class="mini-line__meta">Ароматы: <?= e(implode(', ', array_column($item['config'], 'name'))) ?></span>
                                    <?php endif; ?>
                                </span>
                                <span class="mini-line__price"><?= price_tag($item['line_total'], 'price--inline') ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <dl class="summary-rows">
                        <div class="summary-row">
                            <dt>Товары</dt>
                            <dd><?= price_tag($summary['subtotal'], 'price--inline') ?></dd>
                        </div>
                        <?php if ($summary['discount'] > 0): ?>
                            <div class="summary-row summary-row--accent">
                                <dt>Промокод <?= e($summary['promo']['code']) ?></dt>
                                <dd>−<?= price_tag($summary['discount'], 'price--inline') ?></dd>
                            </div>
                        <?php endif; ?>
                        <div class="summary-row">
                            <dt>Доставка</dt>
                            <dd data-delivery-cost><?= $currentDeliveryCost > 0 ? price_tag($currentDeliveryCost, 'price--inline') : 'Бесплатно' ?></dd>
                        </div>
                    </dl>

                    <div class="summary-total">
                        <span>Итого к оплате</span>
                        <div data-total><?= price($afterDiscount + $currentDeliveryCost, null, 'lg') ?></div>
                    </div>

                    <div class="field">
                        <label class="checkbox">
                            <input type="checkbox" name="consent" value="1" required <?= old('consent') ? 'checked' : '' ?> aria-describedby="consent-error">
                            <span>
                                Согласен(на) на обработку персональных данных в соответствии с
                                <a href="<?= e(url('/privacy')) ?>" target="_blank">Политикой конфиденциальности</a>
                                (Закон РБ № 99-З) и принимаю условия <a href="<?= e(url('/offer')) ?>" target="_blank">публичной оферты</a>
                            </span>
                        </label>
                        <span class="field__error" id="consent-error" role="alert"<?= error('consent') ? '' : ' hidden' ?>><?= e(error('consent') ?? '') ?></span>
                    </div>

                    <button class="btn btn--primary btn--lg btn--block" type="submit">
                        Подтвердить заказ <?= icon('arrow-right') ?>
                    </button>
                    <p class="cart-summary__note"><?= icon('shield') ?> Данные передаются по защищённому соединению</p>
                    <a class="link-muted checkout__back" href="<?= e(url('/cart')) ?>">Вернуться в корзину</a>
                </div>
            </aside>
        </form>
    </div>
</section>
