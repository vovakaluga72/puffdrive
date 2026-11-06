<?php
/**
 * Пример конфигурации Puff Drive.
 *
 * Скопируйте файл в config/config.php и укажите реальные значения.
 * config/config.php добавлен в .gitignore и не попадает в репозиторий.
 */

return [
    'app' => [
        'name'     => 'Puff Drive',
        // Полный адрес сайта без слэша в конце. Если сайт лежит в подпапке — укажите её: https://example.by/shop
        'url'      => 'http://puffdrive.local',
        // true — подробные ошибки на экране (только локально!). На хостинге — false.
        'debug'    => true,
        'timezone' => 'Europe/Minsk',
        // Секрет для подписи служебных данных. Сгенерируйте: php -r "echo bin2hex(random_bytes(32));"
        'key'      => 'change-me-to-random-64-hex-chars',
    ],

    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'puffdrive',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    'session' => [
        'name'     => 'PUFFSESSID',
        'lifetime' => 0,          // 0 — до закрытия браузера
    ],

    'security' => [
        'login_max_attempts'   => 5,   // попыток входа...
        'login_window_minutes' => 15,  // ...за столько минут
        'password_reset_ttl'   => 60,  // срок жизни ссылки восстановления, минут
    ],

    'mail' => [
        'from'      => 'noreply@puffdrive.by',
        'from_name' => 'Puff Drive',
    ],

    'uploads' => [
        'max_size' => 5 * 1024 * 1024, // 5 МБ
        'allowed'  => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
    ],

    'payment' => [
        // Активный шлюз: 'mock' — имитация, 'bepaid' — реальный эквайринг (после подключения)
        'gateway' => 'mock',
        'bepaid'  => [
            'shop_id'    => '',
            'secret_key' => '',
            'test_mode'  => true,
            'api_url'    => 'https://checkout.bepaid.by/ctp/api/checkouts',
        ],
        // Задел под ЕРИП (через bePaid или напрямую через банк-партнёр)
        'erip' => [
            'enabled'      => false,
            'service_code' => '',
        ],
    ],
];
