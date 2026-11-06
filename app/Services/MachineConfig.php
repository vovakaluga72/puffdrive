<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Конфигурация аппарата «Сухой туман»: какой аромат стоит в каждом из слотов.
 *
 * На панели N настраиваемых слотов ароматов (Classic — 4, остальные — 6) и три
 * встроенные функции — «Пауза», «Чернитель шин», «Вызов». Покупатель выбирает
 * только ароматы. Конфигурация хранится в cart_items.options / order_items.options:
 *     {"slots": [1, 2, 3, 4, 5, 6]}   — id ароматов по порядку слотов
 */
final class MachineConfig
{
    /** Встроенные функции панели — не настраиваются. */
    public const FIXED_BUTTONS = ['Пауза', 'Чернитель шин', 'Вызов'];

    /**
     * Проверка на сервере: ровно $required разных существующих активных ароматов.
     *
     * @return array|string нормализованная конфигурация ['slots' => [...]] или текст ошибки
     */
    public static function validate(mixed $slots, int $required): array|string
    {
        if (!is_array($slots)) {
            return "Выберите ароматы для всех слотов ({$required})";
        }

        $ids = [];
        foreach (array_values($slots) as $value) {
            if (!is_scalar($value) || !ctype_digit((string) $value) || (int) $value <= 0) {
                return "Заполните все слоты ароматов ({$required})";
            }
            $ids[] = (int) $value;
        }

        if (count($ids) !== $required) {
            return "Заполните все слоты ароматов ({$required})";
        }
        if (count(array_unique($ids)) !== count($ids)) {
            return 'Один аромат нельзя выбрать дважды';
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $found = (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM aromas WHERE is_active = 1 AND id IN ({$placeholders})",
            $ids
        );
        if ($found !== count($ids)) {
            return 'Некоторые ароматы недоступны. Обновите страницу и выберите заново';
        }

        return ['slots' => $ids];
    }

    /**
     * Человекочитаемый список: [['slot' => 1, 'name' => 'Ocean', 'color' => '#…'], …].
     * Для заказа (этап 4) сохраняется снимок названий, чтобы переименование аромата
     * в справочнике не меняло старые заказы.
     */
    public static function describe(?array $options): array
    {
        $ids = array_map('intval', $options['slots'] ?? []);
        if (!$ids) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $rows = Database::fetchAll("SELECT id, name, color FROM aromas WHERE id IN ({$placeholders})", $ids);
        $byId = array_column($rows, null, 'id');

        $result = [];
        foreach ($ids as $i => $id) {
            $result[] = [
                'slot'  => $i + 1,
                'id'    => $id,
                'name'  => $byId[$id]['name'] ?? 'Аромат #' . $id,
                'color' => $byId[$id]['color'] ?? '#C9A96E',
            ];
        }
        return $result;
    }

    /** Строка «Слот 1 — Ocean, Слот 2 — Dubai, …» для писем и админки. */
    public static function summary(?array $options): string
    {
        return implode(', ', array_map(
            static fn (array $s) => 'Слот ' . $s['slot'] . ' — ' . $s['name'],
            self::describe($options)
        ));
    }
}
