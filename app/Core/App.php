<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Точка сборки приложения: стартует сессию, загружает маршруты
 * и передаёт запрос роутеру.
 */
final class App
{
    public function run(): void
    {
        Session::start();

        // После входа гостевая корзина переносится в аккаунт
        Auth::onLogin([\App\Services\CartService::class, 'mergeGuestCart']);

        $request = Request::capture();
        $router  = new Router();

        // Файл маршрутов получает переменную $router
        require BASE_PATH . '/app/routes.php';

        $router->dispatch($request);
    }
}
