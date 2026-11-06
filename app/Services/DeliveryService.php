<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Способы доставки. Стоимость и порог бесплатной доставки задаются
 * в настройках магазина (админка → «Настройки»).
 */
final class DeliveryService
{
    /**
     * Все способы с текущей стоимостью и набором обязательных полей.
     *
     * fields: какие поля адреса нужны для способа —
     *   city, address, postcode, point (отделение Европочты).
     */
    public static function methods(): array
    {
        return [
            'pickup' => [
                'title'       => 'Самовывоз',
                'description' => setting('delivery_pickup_address', 'г. Гродно, склад магазина'),
                'cost'        => 0.0,
                'free'        => true,
                'fields'      => [],
                'icon'        => 'map-pin',
                'eta'         => 'Сегодня–завтра, после звонка менеджера',
            ],
            'courier' => [
                'title'       => 'Курьер по Гродно',
                'description' => 'Доставка до двери в пределах города',
                'cost'        => (float) setting('delivery_courier_cost', '7'),
                'free'        => false,
                'fields'      => ['address'],
                'icon'        => 'truck',
                'eta'         => '1–2 дня',
            ],
            'europost' => [
                'title'       => 'Европочта',
                'description' => 'До отделения Европочты в любом городе Беларуси',
                'cost'        => (float) setting('delivery_europost_cost', '5.5'),
                'free'        => false,
                'fields'      => ['city', 'point'],
                'icon'        => 'tag',
                'eta'         => '2–4 дня',
            ],
            'belpost' => [
                'title'       => 'Белпочта',
                'description' => 'До почтового отделения по адресу и индексу',
                'cost'        => (float) setting('delivery_belpost_cost', '6'),
                'free'        => false,
                'fields'      => ['city', 'address', 'postcode'],
                'icon'        => 'mail',
                'eta'         => '3–5 дней',
            ],
        ];
    }

    public static function exists(string $method): bool
    {
        return isset(self::methods()[$method]);
    }

    public static function title(string $method): string
    {
        return self::methods()[$method]['title'] ?? $method;
    }

    public static function freeThreshold(): float
    {
        return (float) setting('free_delivery_threshold', '150');
    }

    /** Стоимость доставки для суммы заказа (после скидки по промокоду). */
    public static function cost(string $method, float $amount): float
    {
        $info = self::methods()[$method] ?? null;
        if ($info === null || $info['free']) {
            return 0.0;
        }
        $threshold = self::freeThreshold();
        if ($threshold > 0 && $amount >= $threshold) {
            return 0.0;
        }
        return round($info['cost'], 2);
    }
}
