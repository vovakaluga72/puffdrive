<?php
/**
 * Конфигуратор аппарата: интерактивная панель + выбор аромата для каждого слота.
 * Логика — public/assets/js/configurator.js. На сервере конфигурация
 * повторно проверяется в CartService::add() → MachineConfig::validate().
 *
 * @var array $product
 * @var array $allAromas
 * @var array $fixedButtons
 * @var int   $slots
 */

use App\Core\View;

$aromasJs = array_map(static fn (array $a) => [
    'id'      => (int) $a['id'],
    'name'    => $a['name'],
    'color'   => $a['color'],
    'popular' => (int) $a['is_popular'] === 1,
], $allAromas);
?>
<section class="configurator" id="configurator" aria-labelledby="configurator-title"
         data-configurator data-slots="<?= $slots ?>" data-product="<?= (int) $product['id'] ?>"
         data-aromas="<?= json_attr($aromasJs) ?>">
    <div class="configurator__bg" aria-hidden="true"></div>
    <div class="container">
        <header class="configurator__head">
            <span class="eyebrow">Конфигуратор</span>
            <h2 class="section-title" id="configurator-title">Соберите <em>свою панель</em> ароматов</h2>
            <p class="configurator__text">
                Нажмите на слот слева и выберите аромат. На панели <?= $slots + count($fixedButtons) ?> кнопок:
                <?= $slots ?> <?= plural($slots, ['слот', 'слота', 'слотов']) ?> ароматов настраиваете вы, а «<?= e(implode('», «', $fixedButtons)) ?>» — встроенные функции.
            </p>
        </header>

        <div class="configurator__layout">
            <div class="configurator__machine">
                <?= View::partial('partials/machine-panel', [
                    'slots'       => [],
                    'slotCount'   => $slots,
                    'interactive' => true,
                    'marquee'     => 'АРОМАТ… СУХОЙ ТУМАН · PUFF DRIVE · ВЫБЕРИТЕ АРОМАТ ·',
                    'class'       => 'machine--config',
                ]) ?>
            </div>

            <div class="configurator__side">
                <div class="config-progress">
                    <div class="config-progress__text">
                        Заполнено <strong data-config-filled>0</strong> из <?= $slots ?>
                    </div>
                    <div class="config-progress__bar"><span data-config-bar style="width: 0%"></span></div>
                </div>

                <div class="aroma-picker" data-picker>
                    <div class="aroma-picker__head">
                        <p class="aroma-picker__title" data-picker-title>Слот 1: выберите аромат</p>
                        <button class="link-muted" type="button" data-picker-clear hidden>Очистить слот</button>
                    </div>
                    <div class="aroma-picker__grid" role="listbox" aria-label="Ароматы">
                        <?php foreach ($allAromas as $aroma): ?>
                            <button class="aroma-option" type="button" role="option" aria-selected="false"
                                    data-aroma="<?= (int) $aroma['id'] ?>" style="--aroma: <?= e($aroma['color']) ?>"
                                    title="<?= e($aroma['description'] ?? '') ?>">
                                <span class="aroma-option__icon" aria-hidden="true"></span>
                                <span class="aroma-option__name"><?= e($aroma['name']) ?></span>
                                <span class="aroma-option__state" data-aroma-state></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="configurator__tools">
                    <button class="btn btn--outline btn--sm" type="button" data-config-popular><?= icon('sparkles') ?> Заполнить популярными</button>
                    <button class="btn btn--ghost btn--sm" type="button" data-config-reset><?= icon('refresh') ?> Сбросить</button>
                </div>

                <div class="config-summary">
                    <p class="config-summary__title">Ваша конфигурация</p>
                    <ol class="config-summary__list" data-config-summary></ol>
                    <ul class="config-summary__fixed">
                        <?php foreach ($fixedButtons as $button): ?>
                            <li><?= icon('check') ?> <?= e($button) ?> <span>— встроенная функция</span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <button class="btn btn--primary btn--lg btn--block" type="submit" form="buy-form" data-config-submit disabled>
                    <?= icon('cart') ?> <span data-config-submit-label>Заполните все слоты</span>
                </button>
            </div>
        </div>
    </div>
</section>
