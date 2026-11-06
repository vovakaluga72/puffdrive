<?php
/**
 * Поле формы с подписью, подсказкой и ошибкой валидации.
 * Значение берётся из old() (если форма вернулась с ошибкой), иначе из $value.
 *
 * @var string      $name
 * @var string      $label
 * @var string|null $type          text | email | tel | password | textarea ...
 * @var string|null $value
 * @var bool|null   $required
 * @var string|null $autocomplete
 * @var string|null $placeholder
 * @var string|null $hint
 * @var int|null    $maxlength
 * @var int|null    $minlength
 * @var string|null $inputmode
 * @var string|null $pattern
 * @var string|null $id
 * @var string|null $class         класс обёртки .field
 * @var array|null  $data          data-атрибуты для JS: ['phone' => ''] -> data-phone
 */
$type     = $type ?? 'text';
$id       = $id ?? 'f-' . preg_replace('/[^a-z0-9_-]/i', '-', $name);
$required = $required ?? false;
$error    = error($name);
$isSecret = $type === 'password';
$current  = $isSecret ? '' : (string) old($name, $value ?? '');
$hintId   = $id . '-hint';
$errorId  = $id . '-error';
$describedBy = implode(' ', array_filter([($hint ?? '') !== '' ? $hintId : null, $errorId]));

$attrs = [
    'id'           => $id,
    'name'         => $name,
    'class'        => ($type === 'textarea' ? 'textarea' : 'input') . ($error ? ' is-invalid' : ''),
    'autocomplete' => $autocomplete ?? null,
    'placeholder'  => $placeholder ?? null,
    'maxlength'    => $maxlength ?? null,
    'minlength'    => $minlength ?? null,
    'inputmode'    => $inputmode ?? null,
    'pattern'      => $pattern ?? null,
    'aria-invalid' => $error ? 'true' : null,
    'aria-describedby' => $describedBy,
];
foreach ($data ?? [] as $key => $val) {
    $attrs['data-' . $key] = (string) $val;
}

$attrString = '';
foreach ($attrs as $attr => $val) {
    if ($val !== null && $val !== '') {
        $attrString .= ' ' . $attr . '="' . e($val) . '"';
    } elseif ($val === '' && str_starts_with($attr, 'data-')) {
        $attrString .= ' ' . $attr;
    }
}
if ($required) {
    $attrString .= ' required';
}
?>
<div class="field <?= e($class ?? '') ?>">
    <label class="field__label" for="<?= e($id) ?>">
        <?= e($label) ?><?php if ($required): ?> <span class="req" aria-hidden="true">*</span><?php endif; ?>
    </label>

    <?php if ($type === 'textarea'): ?>
        <textarea<?= $attrString ?> rows="4"><?= e($current) ?></textarea>
    <?php elseif ($isSecret): ?>
        <div class="input-group">
            <input type="password"<?= $attrString ?>>
            <button class="input-group__btn" type="button" aria-label="Показать пароль" aria-pressed="false" data-password-toggle>
                <?= icon('eye') ?>
            </button>
        </div>
    <?php else: ?>
        <input type="<?= e($type) ?>"<?= $attrString ?> value="<?= e($current) ?>">
    <?php endif; ?>

    <?php if (($hint ?? '') !== ''): ?>
        <span class="field__hint" id="<?= e($hintId) ?>"><?= e($hint) ?></span>
    <?php endif; ?>
    <span class="field__error" id="<?= e($errorId) ?>" role="alert"<?= $error ? '' : ' hidden' ?>><?= e($error ?? '') ?></span>
</div>
