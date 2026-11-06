<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Ожидаемая ошибка оформления заказа (закончился товар, исчерпан промокод).
 * Текст показывается покупателю, транзакция откатывается.
 */
final class OrderException extends \RuntimeException
{
}
