<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Core\View;

/**
 * Отправка писем через стандартную функцию mail() — она есть на любом
 * виртуальном хостинге и не требует внешних библиотек.
 * Письмо формируется как multipart/alternative: текстовая и HTML-версии.
 */
final class Mailer
{
    /**
     * @param string $template шаблон из app/Views/emails (без .php)
     * @param array  $data     данные для шаблона
     * @return bool  удалось ли передать письмо почтовой системе
     */
    public static function send(string $to, string $subject, string $template, array $data = []): bool
    {
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        $html = View::render('emails/' . $template, $data + ['subject' => $subject], 'emails/layout');
        $text = self::htmlToText($html);

        $from     = (string) config('mail.from', 'noreply@localhost');
        $fromName = (string) config('mail.from_name', 'Puff Drive');
        $boundary = 'pd_' . bin2hex(random_bytes(12));

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'From: ' . self::encodeHeader($fromName) . ' <' . $from . '>',
            'Reply-To: ' . (setting('email') !== '' ? setting('email') : $from),
            'X-Mailer: PuffDrive',
        ];

        $body = "--{$boundary}\r\n"
              . "Content-Type: text/plain; charset=UTF-8\r\n"
              . "Content-Transfer-Encoding: base64\r\n\r\n"
              . chunk_split(base64_encode($text)) . "\r\n"
              . "--{$boundary}\r\n"
              . "Content-Type: text/html; charset=UTF-8\r\n"
              . "Content-Transfer-Encoding: base64\r\n\r\n"
              . chunk_split(base64_encode($html)) . "\r\n"
              . "--{$boundary}--\r\n";

        try {
            // @ — mail() на Windows/без настроенного SMTP выдаёт warning; результат проверяем сами
            $sent = @mail($to, self::encodeHeader($subject), $body, implode("\r\n", $headers));
        } catch (\Throwable $e) {
            $sent = false;
        }

        if (!$sent) {
            Logger::warning('Не удалось отправить письмо', ['to' => $to, 'subject' => $subject]);
        }
        return $sent;
    }

    /** Заголовки с кириллицей кодируются по RFC 2047. */
    private static function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    /** Текстовая версия письма для почтовых клиентов без HTML. */
    private static function htmlToText(string $html): string
    {
        // Ссылки превращаем в «текст (адрес)»
        $html = preg_replace('#<a[^>]+href="([^"]+)"[^>]*>(.*?)</a>#is', '$2 ($1)', $html) ?? $html;
        $html = preg_replace('#<(br|/p|/h\d|/tr|/div)[^>]*>#i', "\n", $html) ?? $html;
        $html = preg_replace('#<(style|head)[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/\n\s*\n+/u", "\n\n", $text) ?? $text;
        return trim($text);
    }
}
