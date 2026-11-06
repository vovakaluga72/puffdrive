<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Глобальная обработка ошибок.
 * В продакшене (debug = false) пользователь видит аккуратную страницу 500,
 * а подробности пишутся в лог. В debug-режиме выводится стек вызовов.
 */
final class ErrorHandler
{
    private static bool $debug = false;

    public static function register(bool $debug): void
    {
        self::$debug = $debug;

        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');

        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function isDebug(): bool
    {
        return self::$debug;
    }

    /** Предупреждения PHP превращаем в исключения — так ошибки не теряются. */
    public static function handleError(int $severity, string $message, string $file = '', int $line = 0): bool
    {
        if (!(error_reporting() & $severity)) {
            return false; // ошибка подавлена оператором @
        }
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    public static function handleException(Throwable $e): void
    {
        $status = $e instanceof HttpException ? $e->status() : 500;

        if ($status >= 500) {
            Logger::error($e->getMessage(), [
                'type' => $e::class,
                'file' => $e->getFile() . ':' . $e->getLine(),
                'url'  => $_SERVER['REQUEST_URI'] ?? '',
                'trace' => self::$debug ? null : $e->getTraceAsString(),
            ]);
        }

        // Сбрасываем недорисованный HTML
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code($status);
        }

        $wantsJson = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

        if ($wantsJson) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            $message = $e instanceof HttpException ? $e->getMessage() : HttpException::defaultMessage(500);
            echo json_encode([
                'ok'    => false,
                'error' => self::$debug && !$e instanceof HttpException ? $e->getMessage() : $message,
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (self::$debug && !$e instanceof HttpException) {
            self::renderDebug($e);
            return;
        }

        self::renderErrorPage($status, $e instanceof HttpException ? $e->getMessage() : '');
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            self::handleException(new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']));
        }
    }

    /** Страница ошибки в стиле сайта; если и она не рисуется (например, упала БД) — простой запасной HTML. */
    private static function renderErrorPage(int $status, string $message): void
    {
        $view = match (true) {
            $status === 404 => 'errors/404',
            $status >= 500  => 'errors/500',
            default         => 'errors/generic',
        };

        try {
            echo View::render($view, [
                'title'   => $status === 404 ? 'Страница не найдена' : 'Ошибка ' . $status,
                'status'  => $status,
                'message' => $message !== '' ? $message : HttpException::defaultMessage($status),
            ], $status >= 500 ? 'layouts/minimal' : 'layouts/main');
        } catch (Throwable $inner) {
            Logger::error('Ошибка при показе страницы ошибки: ' . $inner->getMessage());
            echo '<!doctype html><html lang="ru"><meta charset="utf-8"><title>Puff Drive</title>'
               . '<body style="background:#0b0b0d;color:#f3efe8;font-family:system-ui;display:grid;place-items:center;min-height:100vh;margin:0;text-align:center">'
               . '<div><h1 style="font-weight:600">Сайт временно недоступен</h1>'
               . '<p style="color:#a19d96">Мы уже разбираемся. Попробуйте обновить страницу через пару минут.</p></div></body></html>';
        }
    }

    private static function renderDebug(Throwable $e): void
    {
        echo '<!doctype html><html lang="ru"><meta charset="utf-8"><title>Ошибка: ' . e($e->getMessage()) . '</title>'
           . '<body style="background:#111;color:#eee;font:14px/1.5 Consolas,monospace;padding:24px">'
           . '<h1 style="color:#ff6b6b;font-size:20px">' . e($e::class) . '</h1>'
           . '<p style="font-size:16px">' . e($e->getMessage()) . '</p>'
           . '<p style="color:#c9a96e">' . e($e->getFile()) . ':' . $e->getLine() . '</p>'
           . '<pre style="background:#1b1b1b;padding:16px;overflow:auto;border-radius:8px">' . e($e->getTraceAsString()) . '</pre>'
           . '<p style="color:#888">Режим отладки включён (app.debug = true). На хостинге выключите его в config/config.php.</p>'
           . '</body></html>';
    }
}
