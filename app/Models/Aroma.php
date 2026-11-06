<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Справочник ароматов — для фильтров каталога и конфигуратора аппарата.
 */
final class Aroma
{
    public static function active(): array
    {
        return Database::fetchAll(
            'SELECT id, slug, name, color, description, is_popular
               FROM aromas
              WHERE is_active = 1
              ORDER BY sort_order, name'
        );
    }

    /** Количество активных ароматов (для статистики на главной). */
    public static function countActive(): int
    {
        return (int) Database::fetchColumn('SELECT COUNT(*) FROM aromas WHERE is_active = 1');
    }
}
