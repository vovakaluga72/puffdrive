<?php
/**
 * Письмо со ссылкой восстановления пароля.
 *
 * @var string $name
 * @var string $link
 * @var int    $ttl срок действия ссылки, минут
 */
?>
<h1 style="margin:0 0 16px;font-family:Georgia,'Times New Roman',serif;font-size:24px;font-weight:normal;color:#f3efe8;">
    Восстановление пароля
</h1>
<p style="margin:0 0 16px;">Здравствуйте, <?= e($name) ?>!</p>
<p style="margin:0 0 24px;">
    Мы получили запрос на смену пароля для вашего аккаунта Puff Drive.
    Нажмите на кнопку, чтобы задать новый пароль:
</p>
<p style="margin:0 0 24px;">
    <a href="<?= e($link) ?>" style="display:inline-block;padding:14px 28px;background:#c9a96e;color:#0b0b0d;font-weight:bold;text-decoration:none;border-radius:999px;">
        Задать новый пароль
    </a>
</p>
<p style="margin:0 0 16px;font-size:13px;color:#a8a39b;">
    Ссылка действует <?= (int) $ttl ?> минут и сработает только один раз.
    Если кнопка не нажимается, скопируйте адрес в браузер:<br>
    <a href="<?= e($link) ?>" style="color:#c9a96e;word-break:break-all;"><?= e($link) ?></a>
</p>
<p style="margin:0;font-size:13px;color:#a8a39b;">
    Если вы не запрашивали восстановление — просто проигнорируйте это письмо, пароль останется прежним.
</p>
