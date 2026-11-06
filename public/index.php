<?php
/**
 * Фронт-контроллер Puff Drive.
 * Любой запрос к сайту (кроме статических файлов) попадает сюда.
 */

declare(strict_types=1);

// Для встроенного сервера PHP (php -S): статические файлы отдаём напрямую
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($file !== __DIR__ . '/' && is_file($file)) {
        return false;
    }
}

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/bootstrap.php';

$app = new App\Core\App();
$app->run();
