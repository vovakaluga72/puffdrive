<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Защита от CSRF: один токен на сессию.
 * Формы передают его в скрытом поле _csrf, AJAX — в заголовке X-CSRF-Token.
 */
final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        $token = Session::get(self::KEY);
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            Session::set(self::KEY, $token);
        }
        return $token;
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function verify(Request $request): void
    {
        $sent = $request->header('X-CSRF-Token') ?? $request->input('_csrf');
        $real = Session::get(self::KEY);

        if (!is_string($sent) || !is_string($real) || !hash_equals($real, $sent)) {
            throw new HttpException(419);
        }
    }

    /** Новый токен — после входа/выхода. */
    public static function refresh(): void
    {
        Session::set(self::KEY, bin2hex(random_bytes(32)));
    }
}
