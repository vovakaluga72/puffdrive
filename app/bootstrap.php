<?php
/**
 * Начальная загрузка приложения: автозагрузчик классов, хелперы,
 * конфигурация, часовой пояс и глобальный обработчик ошибок.
 */

declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// PSR-4-подобный автозагрузчик без Composer: App\Core\Router -> app/Core/Router.php
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = BASE_PATH . '/app/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . '/app/helpers.php';

App\Core\Config::load(BASE_PATH . '/config');

date_default_timezone_set((string) config('app.timezone', 'Europe/Minsk'));
mb_internal_encoding('UTF-8');

App\Core\ErrorHandler::register((bool) config('app.debug', false));
