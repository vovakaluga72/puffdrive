<?php
/**
 * Маршруты сайта. Переменная $router передаётся из App::run().
 * Новые разделы добавляются здесь по мере реализации этапов.
 *
 * Middleware: 'guest' — только для гостей, 'auth' — только для вошедших,
 * 'admin' — только для администраторов. CSRF проверяется для всех POST автоматически.
 *
 * @var App\Core\Router $router
 */

declare(strict_types=1);

use App\Controllers\AccountController;
use App\Controllers\AddressController;
use App\Controllers\AuthController;
use App\Controllers\CartController;
use App\Controllers\CatalogController;
use App\Controllers\CheckoutController;
use App\Controllers\HomeController;
use App\Controllers\OrderController;
use App\Controllers\PasswordController;
use App\Controllers\PaymentController;
use App\Controllers\ProductController;

// Главная (с приветственным экраном при первом заходе)
$router->get('/', [HomeController::class, 'index']);

// Разделы в разработке: /promotions, /business, /delivery, /about, /contacts, /privacy, /offer
$router->get('/{page:' . implode('|', App\Controllers\PageController::slugs()) . '}', [App\Controllers\PageController::class, 'soon']);

// ------------------------------------------------------------------
// Каталог, поиск, товар
// ------------------------------------------------------------------
$router->get('/catalog', [CatalogController::class, 'index']);
$router->get('/catalog/{slug:[a-z0-9-]+}', [CatalogController::class, 'category']);
$router->get('/search', [CatalogController::class, 'search']);
$router->get('/search/suggest', [CatalogController::class, 'suggest']);
$router->get('/product/{slug:[a-z0-9-]+}', [ProductController::class, 'show']);

// ------------------------------------------------------------------
// Корзина, оформление заказа, оплата
// ------------------------------------------------------------------
$router->get('/cart', [CartController::class, 'index']);
$router->post('/cart/add', [CartController::class, 'add']);
$router->post('/cart/update', [CartController::class, 'update']);
$router->post('/cart/remove', [CartController::class, 'remove']);
$router->post('/cart/promo', [CartController::class, 'applyPromo']);
$router->post('/cart/promo/remove', [CartController::class, 'removePromo']);

$router->get('/checkout', [CheckoutController::class, 'show']);
$router->post('/checkout', [CheckoutController::class, 'place']);

$router->get('/order/{number:PD-\d{6}-\d{4,}}', [OrderController::class, 'show']);
$router->get('/payment/{number:PD-\d{6}-\d{4,}}', [PaymentController::class, 'show']);
$router->post('/payment/{number:PD-\d{6}-\d{4,}}/mock', [PaymentController::class, 'mock']);
// Уведомления платёжных систем: без CSRF, подлинность проверяет шлюз по подписи
$router->post('/payment/callback/{provider:[a-z]+}', [PaymentController::class, 'callback'], ['nocsrf']);

// ------------------------------------------------------------------
// Вход, регистрация, восстановление пароля
// ------------------------------------------------------------------
$router->get('/login', [AuthController::class, 'showLogin'], ['guest']);
$router->post('/login', [AuthController::class, 'login'], ['guest']);
$router->get('/register', [AuthController::class, 'showRegister'], ['guest']);
$router->post('/register', [AuthController::class, 'register'], ['guest']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth']);

$router->get('/password/forgot', [PasswordController::class, 'showForgot'], ['guest']);
$router->post('/password/forgot', [PasswordController::class, 'sendLink'], ['guest']);
$router->get('/password/reset/{token:[a-f0-9]{64}}', [PasswordController::class, 'showReset'], ['guest']);
$router->post('/password/reset', [PasswordController::class, 'reset'], ['guest']);

// ------------------------------------------------------------------
// Личный кабинет
// ------------------------------------------------------------------
$router->group('/account', ['auth'], static function (App\Core\Router $r): void {
    $r->get('/', [AccountController::class, 'index']);
    $r->post('/profile', [AccountController::class, 'updateProfile']);
    $r->post('/password', [AccountController::class, 'updatePassword']);
    $r->get('/orders', [AccountController::class, 'orders']);
    $r->get('/orders/{number:PD-\d{6}-\d{4,}}', [OrderController::class, 'accountShow']);
    $r->get('/reviews', [AccountController::class, 'reviews']);

    $r->get('/addresses', [AddressController::class, 'index']);
    $r->post('/addresses', [AddressController::class, 'store']);
    $r->post('/addresses/{id:\d+}', [AddressController::class, 'update']);
    $r->post('/addresses/{id:\d+}/default', [AddressController::class, 'makeDefault']);
    $r->post('/addresses/{id:\d+}/delete', [AddressController::class, 'delete']);
});
