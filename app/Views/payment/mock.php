<?php
/**
 * Имитация платёжной страницы банка.
 * ВАЖНО: поля карты не имеют атрибута name — их значения не отправляются
 * на сервер и нигде не сохраняются. Отправляется только выбранный результат.
 *
 * @var array  $order
 * @var array  $payment
 * @var string $orderUrl
 */
?>
<section class="payment-page">
    <div class="container payment-page__inner">
        <div class="payment-card">
            <div class="payment-card__test">
                <?= icon('alert') ?>
                <span><strong>Тестовый режим.</strong> Реальная оплата не производится, данные карты никуда не передаются.</span>
            </div>

            <div class="payment-card__head">
                <div>
                    <p class="payment-card__label">Оплата заказа</p>
                    <p class="payment-card__number"><?= e($order['number']) ?></p>
                </div>
                <div class="payment-card__amount"><?= price((float) $order['total'], null, 'lg') ?></div>
            </div>

            <!-- Визуальная карта -->
            <div class="bank-card" aria-hidden="true">
                <div class="bank-card__chip"></div>
                <div class="bank-card__number" data-card-preview>4242 4242 4242 4242</div>
                <div class="bank-card__row">
                    <span data-holder-preview>TEST CARDHOLDER</span>
                    <span data-exp-preview>12/30</span>
                </div>
                <div class="bank-card__brand">PUFF · PAY</div>
            </div>

            <div class="payment-form">
                <div class="field">
                    <label class="field__label" for="card-number">Номер карты</label>
                    <input class="input" id="card-number" type="text" inputmode="numeric" autocomplete="off"
                           value="4242 4242 4242 4242" maxlength="19" data-card-number>
                </div>
                <div class="form__grid">
                    <div class="field">
                        <label class="field__label" for="card-exp">Срок действия</label>
                        <input class="input" id="card-exp" type="text" inputmode="numeric" autocomplete="off" value="12/30" maxlength="5" data-card-exp>
                    </div>
                    <div class="field">
                        <label class="field__label" for="card-cvc">CVC</label>
                        <input class="input" id="card-cvc" type="password" inputmode="numeric" autocomplete="off" value="123" maxlength="3">
                    </div>
                </div>
                <div class="field">
                    <label class="field__label" for="card-holder">Владелец карты</label>
                    <input class="input" id="card-holder" type="text" autocomplete="off" value="TEST CARDHOLDER" data-card-holder>
                </div>
            </div>

            <div class="payment-actions">
                <form action="<?= e(url('/payment/' . $order['number'] . '/mock')) ?>" method="post" data-pay-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= e($order['access_token']) ?>">
                    <input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>">
                    <input type="hidden" name="result" value="success">
                    <button class="btn btn--primary btn--lg btn--block" type="submit"><?= icon('check') ?> Успешная оплата</button>
                </form>
                <form action="<?= e(url('/payment/' . $order['number'] . '/mock')) ?>" method="post" data-pay-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= e($order['access_token']) ?>">
                    <input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>">
                    <input type="hidden" name="result" value="fail">
                    <button class="btn btn--danger btn--lg btn--block" type="submit"><?= icon('close') ?> Ошибка оплаты</button>
                </form>
            </div>

            <p class="payment-card__footer">
                Транзакция <?= e((string) $payment['transaction_id']) ?> ·
                <a href="<?= e($orderUrl) ?>">Вернуться к заказу</a>
            </p>
        </div>
    </div>
</section>
