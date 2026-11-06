<?php
/**
 * Личный кабинет — сохранённые адреса доставки.
 *
 * @var array      $user
 * @var array      $stats
 * @var array      $addresses
 * @var array|null $edit    редактируемый адрес (?edit=ID)
 * @var bool       $canAdd
 */

use App\Core\View;
use App\Models\Address;

$formAction = $edit ? url('/account/addresses/' . $edit['id']) : url('/account/addresses');
$hasErrors = (bool) flash('errors');
$showForm = $edit !== null || $hasErrors || !$addresses;
?>
<section class="account">
    <div class="container">
        <?= View::partial('account/_nav', ['user' => $user, 'stats' => $stats, 'section' => 'addresses']) ?>

        <div class="account-grid account-grid--addresses">
            <div class="panel">
                <div class="panel__head">
                    <h1 class="panel__title">Мои адреса</h1>
                    <?php if ($addresses && $canAdd && !$showForm): ?>
                        <a class="btn btn--outline btn--sm" href="#address-form" data-reveal-form="address-form"><?= icon('plus') ?> Добавить адрес</a>
                    <?php endif; ?>
                </div>

                <?php if ($addresses): ?>
                    <ul class="address-list">
                        <?php foreach ($addresses as $address): ?>
                            <li class="address-card<?= (int) $address['is_default'] === 1 ? ' is-default' : '' ?><?= $edit && (int) $edit['id'] === (int) $address['id'] ? ' is-editing' : '' ?>">
                                <div class="address-card__head">
                                    <span class="address-card__title"><?= icon('map-pin') ?> <?= e($address['title']) ?></span>
                                    <?php if ((int) $address['is_default'] === 1): ?>
                                        <span class="badge badge--accent">Основной</span>
                                    <?php endif; ?>
                                </div>
                                <p class="address-card__line"><?= e(Address::format($address)) ?></p>
                                <?php if ($address['recipient'] || $address['phone']): ?>
                                    <p class="address-card__muted">
                                        <?= e(trim(($address['recipient'] ?? '') . ($address['phone'] ? ' · ' . format_phone($address['phone']) : ''), ' ·')) ?>
                                    </p>
                                <?php endif; ?>

                                <div class="address-card__actions">
                                    <a class="btn btn--ghost btn--sm" href="<?= e(url('/account/addresses', ['edit' => $address['id']])) ?>#address-form">Изменить</a>
                                    <?php if ((int) $address['is_default'] !== 1): ?>
                                        <form action="<?= e(url('/account/addresses/' . $address['id'] . '/default')) ?>" method="post">
                                            <?= csrf_field() ?>
                                            <button class="btn btn--ghost btn--sm" type="submit">Сделать основным</button>
                                        </form>
                                    <?php endif; ?>
                                    <form action="<?= e(url('/account/addresses/' . $address['id'] . '/delete')) ?>" method="post"
                                          data-confirm="Удалить адрес «<?= e($address['title']) ?>»?">
                                        <?= csrf_field() ?>
                                        <button class="icon-btn icon-btn--danger" type="submit" aria-label="Удалить адрес «<?= e($address['title']) ?>»"><?= icon('trash') ?></button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty-state empty-state--compact">
                        <span class="empty-state__icon"><?= icon('map-pin') ?></span>
                        <p class="empty-state__text">Сохраните адрес — при оформлении заказа он подставится автоматически.</p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($canAdd || $edit): ?>
                <div class="panel" id="address-form" <?= $showForm ? '' : 'hidden' ?>>
                    <h2 class="panel__title"><?= $edit ? 'Изменить адрес' : 'Новый адрес' ?></h2>
                    <p class="panel__text">Для Белпочты укажите индекс, для Европочты — адрес удобного отделения.</p>

                    <form class="form" action="<?= e($formAction) ?>" method="post" novalidate data-validate>
                        <?= csrf_field() ?>

                        <div class="form__grid">
                            <?= View::partial('partials/form-field', [
                                'name' => 'title', 'label' => 'Название', 'value' => $edit['title'] ?? '',
                                'maxlength' => 60, 'placeholder' => 'Дом, Работа, Автомойка',
                            ]) ?>
                            <?= View::partial('partials/form-field', [
                                'name' => 'city', 'label' => 'Город', 'required' => true, 'value' => $edit['city'] ?? '',
                                'autocomplete' => 'address-level2', 'minlength' => 2, 'maxlength' => 100, 'placeholder' => 'Гродно',
                            ]) ?>
                        </div>

                        <?= View::partial('partials/form-field', [
                            'name' => 'address', 'label' => 'Улица, дом, квартира', 'required' => true, 'value' => $edit['address'] ?? '',
                            'autocomplete' => 'street-address', 'minlength' => 3, 'maxlength' => 255, 'placeholder' => 'ул. Советская, д. 10, кв. 5',
                        ]) ?>

                        <div class="form__grid form__grid--3">
                            <?= View::partial('partials/form-field', [
                                'name' => 'postcode', 'label' => 'Индекс', 'value' => $edit['postcode'] ?? '',
                                'autocomplete' => 'postal-code', 'inputmode' => 'numeric', 'maxlength' => 6, 'pattern' => '\d{6}',
                                'placeholder' => '230025',
                            ]) ?>
                            <?= View::partial('partials/form-field', [
                                'name' => 'recipient', 'label' => 'Получатель', 'value' => $edit['recipient'] ?? $user['name'],
                                'autocomplete' => 'name', 'maxlength' => 100,
                            ]) ?>
                            <?= View::partial('partials/form-field', [
                                'name' => 'phone', 'label' => 'Телефон получателя', 'type' => 'tel',
                                'value' => format_phone($edit['phone'] ?? $user['phone']),
                                'autocomplete' => 'tel', 'inputmode' => 'tel', 'placeholder' => '+375 (29) 123-45-67',
                                'data' => ['phone' => ''],
                            ]) ?>
                        </div>

                        <?php if (!$edit || (int) $edit['is_default'] !== 1): ?>
                            <label class="checkbox">
                                <input type="checkbox" name="is_default" value="1" <?= old('is_default') ? 'checked' : '' ?>>
                                <span>Сделать основным адресом</span>
                            </label>
                        <?php endif; ?>

                        <div class="form__actions">
                            <button class="btn btn--primary" type="submit"><?= $edit ? 'Сохранить' : 'Добавить адрес' ?></button>
                            <?php if ($edit): ?>
                                <a class="btn btn--ghost" href="<?= e(url('/account/addresses')) ?>">Отмена</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
