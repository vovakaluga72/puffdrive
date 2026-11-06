<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * HTTP-ошибка (404, 403, 419 и т.д.), которую можно бросить из любого места.
 * ErrorHandler покажет для неё страницу в стиле сайта.
 */
final class HttpException extends RuntimeException
{
    public function __construct(private int $status, string $message = '')
    {
        parent::__construct($message !== '' ? $message : self::defaultMessage($status), $status);
    }

    public function status(): int
    {
        return $this->status;
    }

    public static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'Некорректный запрос',
            403 => 'Доступ запрещён',
            404 => 'Страница не найдена',
            405 => 'Метод не поддерживается',
            419 => 'Сессия устарела. Обновите страницу и повторите действие.',
            422 => 'Проверьте правильность заполнения формы',
            429 => 'Слишком много запросов. Попробуйте позже.',
            default => 'Внутренняя ошибка сервера',
        };
    }
}
