<?php
/**
 * Общий шаблон писем. Только табличная вёрстка и inline-стили —
 * так письмо одинаково выглядит в Gmail, Mail.ru, Яндекс Почте и Outlook.
 *
 * @var string $content
 * @var string $subject
 */
$shop  = setting('shop_name', 'Puff Drive');
$phone = setting('phone');
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($subject) ?></title>
</head>
<body style="margin:0;padding:0;background:#0b0b0d;font-family:Arial,Helvetica,sans-serif;color:#f3efe8;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0b0b0d;padding:32px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#141417;border:1px solid #26262c;border-radius:16px;">
                <tr>
                    <td style="padding:28px 32px;border-bottom:1px solid #26262c;">
                        <span style="font-family:Georgia,'Times New Roman',serif;font-size:26px;font-weight:bold;color:#f3efe8;">Puff</span><span style="font-family:Georgia,'Times New Roman',serif;font-size:26px;font-weight:bold;color:#c9a96e;"> Drive</span>
                        <div style="font-size:11px;letter-spacing:3px;color:#8a857e;text-transform:uppercase;margin-top:4px;"><?= e(setting('shop_tagline', 'Сухой туман')) ?></div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px;font-size:15px;line-height:1.6;color:#e6e1d9;">
                        <?= $content ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 32px;border-top:1px solid #26262c;font-size:12px;line-height:1.6;color:#8a857e;">
                        <?= e($shop) ?> · <?= e(format_phone($phone)) ?> · <?= e(setting('email')) ?><br>
                        <?= e(setting('address')) ?><br>
                        Это автоматическое письмо, отвечать на него не нужно.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
