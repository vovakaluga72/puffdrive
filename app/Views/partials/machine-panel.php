<?php
/**
 * Лицевая панель аппарата «Сухой туман» в стиле реального устройства:
 * чёрная глянцевая панель, LED-табло с бегущей строкой, слоты ароматов
 * слева и 3 фиксированные кнопки справа (Пауза, Чернитель шин, Вызов).
 *
 * На главной панель декоративная. На странице аппарата (этап 3) используется
 * в конфигураторе с интерактивными слотами.
 *
 * @var int|null    $slotCount   число слотов ароматов (по умолчанию 6)
 * @var array       $slots       элементы: ['name' => 'Ocean', 'color' => '#3FA7D6'] или null (пусто)
 * @var bool|null   $interactive кнопки-слоты кликабельны (конфигуратор)
 * @var string|null $marquee     текст бегущей строки
 * @var string|null $class       дополнительный CSS-класс
 */
$interactive = $interactive ?? false;
$marquee = $marquee ?? 'АРОМАТ · СУХОЙ ТУМАН · PUFF DRIVE · ВЫБЕРИТЕ АРОМАТ ·';
$slotCount = $slotCount ?? 6;
$slots = array_pad(array_slice($slots ?? [], 0, $slotCount), $slotCount, null);
$fixed = [
    ['key' => 'pause', 'name' => 'Пауза'],
    ['key' => 'tire',  'name' => 'Чернитель шин'],
    ['key' => 'call',  'name' => 'Вызов'],
];
$slotTag = $interactive ? 'button' : 'div';
?>
<div class="machine <?= e($class ?? '') ?><?= $interactive ? ' machine--interactive' : '' ?>" data-machine>
    <div class="machine__top">
        <div class="machine__led" aria-hidden="true">
            <div class="machine__led-track">
                <span><?= e($marquee) ?>&nbsp;</span><span><?= e($marquee) ?>&nbsp;</span>
            </div>
        </div>
    </div>

    <div class="machine__panel">
        <div class="machine__col machine__col--aromas" role="<?= $interactive ? 'group' : 'presentation' ?>" aria-label="Слоты ароматов">
            <?php foreach ($slots as $i => $slot): ?>
                <<?= $slotTag ?> class="pill<?= $slot ? ' is-filled' : '' ?>"
                    <?= $interactive ? 'type="button" data-slot="' . $i . '" aria-label="Слот ' . ($i + 1) . ($slot ? ': ' . e($slot['name']) : ': не выбран') . '"' : '' ?>
                    style="--aroma: <?= e($slot['color'] ?? '#3a3a42') ?>; --i: <?= $i ?>">
                    <span class="pill__led" aria-hidden="true"></span>
                    <span class="pill__label" data-slot-label><?= e($slot['name'] ?? 'Слот ' . ($i + 1)) ?></span>
                    <span class="pill__num" aria-hidden="true"><?= $i + 1 ?></span>
                </<?= $slotTag ?>>
            <?php endforeach; ?>
        </div>

        <div class="machine__col machine__col--fixed">
            <?php foreach ($fixed as $btn): ?>
                <div class="pill pill--fixed pill--<?= e($btn['key']) ?>" aria-disabled="true" title="Встроенная функция">
                    <span class="pill__led" aria-hidden="true"></span>
                    <span class="pill__label"><?= e($btn['name']) ?></span>
                    <?php if ($interactive): ?><span class="pill__hint">встроенная функция</span><?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div class="machine__pay" aria-hidden="true">
                <div class="machine__qr">
                    <svg viewBox="0 0 21 21" shape-rendering="crispEdges">
                        <path fill="currentColor" d="M0 0h7v7H0zM1 1v5h5V1zM2 2h3v3H2zM14 0h7v7h-7zm1 1v5h5V1zm1 1h3v3h-3zM0 14h7v7H0zm1 1v5h5v-5zm1 1h3v3H2zM8 0h2v2H8zM11 1h2v3h-2zM8 3h2v2H8zM9 6h3v2H9zM8 9h2v2H8zM0 8h2v2H0zM3 8h3v2H3zM12 9h3v2h-3zM16 8h2v3h-2zM19 9h2v2h-2zM11 12h2v2h-2zM14 12h3v2h-3zM18 13h3v2h-3zM8 13h2v3H8zM11 15h2v3h-2zM14 15h2v2h-2zM17 16h2v3h-2zM8 18h3v3H8zM12 19h4v2h-4zM19 19h2v2h-2z"/>
                    </svg>
                </div>
                <div class="machine__cash">
                    <span class="machine__slot"></span>
                    <span class="machine__slot machine__slot--coin"></span>
                </div>
            </div>
        </div>
    </div>

    <div class="machine__base" aria-hidden="true">
        <span>НАЛИЧНЫЕ · QR</span>
    </div>
    <div class="machine__hose" aria-hidden="true"></div>
</div>
