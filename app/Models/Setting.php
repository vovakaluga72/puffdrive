<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;

/**
 * Настройки магазина (таблица settings, «ключ — значение»).
 * Загружаются одним запросом и кэшируются на время запроса.
 */
final class Setting
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            try {
                $rows = Database::fetchAll('SELECT setting_key, setting_value FROM settings');
                self::$cache = array_column($rows, 'setting_value', 'setting_key');
            } catch (\PDOException $e) {
                // Без настроек сайт всё равно должен открыться (например, на странице ошибки)
                Logger::error('Не удалось загрузить настройки: ' . $e->getMessage());
                self::$cache = [];
            }
        }
        return self::$cache;
    }

    public static function get(string $key, string $default = ''): string
    {
        $value = self::all()[$key] ?? null;
        return $value === null || $value === '' ? $default : (string) $value;
    }

    /** Сохранение набора настроек (используется в админке). */
    public static function save(array $values): void
    {
        foreach ($values as $key => $value) {
            Database::query(
                'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
                [(string) $key, (string) $value]
            );
        }
        self::$cache = null;
    }

    /** Ссылки на соцсети: только заполненные. */
    public static function socials(): array
    {
        $map = [
            'telegram'  => 'Telegram',
            'instagram' => 'Instagram',
            'viber'     => 'Viber',
            'tiktok'    => 'TikTok',
        ];
        $result = [];
        foreach ($map as $key => $title) {
            $url = self::get('social_' . $key);
            if ($url !== '') {
                $result[] = ['key' => $key, 'title' => $title, 'url' => $url];
            }
        }
        return $result;
    }
}
