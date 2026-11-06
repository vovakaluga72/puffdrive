<?php
/**
 * Контейнер всплывающих уведомлений. Flash-сообщения из сессии
 * (success / error / info) передаются в JS и показываются как тосты.
 */
$messages = [];
foreach (['success', 'error', 'info'] as $type) {
    $text = flash($type);
    if (is_string($text) && $text !== '') {
        $messages[] = ['type' => $type, 'text' => $text];
    }
}
?>
<div class="toasts" aria-live="polite" aria-atomic="false" data-toasts
     <?= $messages ? 'data-initial="' . json_attr($messages) . '"' : '' ?>></div>
