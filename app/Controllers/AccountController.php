<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;

/**
 * Личный кабинет: профиль, смена пароля, история заказов, мои отзывы.
 * Все методы доступны только авторизованным (middleware 'auth' в routes.php).
 */
final class AccountController extends Controller
{
    private function page(string $view, string $title, string $section, array $data = []): string
    {
        $user = Auth::user();
        return $this->view($view, $data + [
            'title'     => $title,
            'section'   => $section,
            'user'      => $user,
            'stats'     => User::stats((int) $user['id']),
            'bodyClass' => 'page-account',
            'styles'    => ['account.css'],
            'scripts'   => ['forms.js'],
        ]);
    }

    public function index(): string
    {
        $orders = Order::forUser((int) Auth::id());
        return $this->page('account/profile', 'Личный кабинет', 'profile', [
            'recentOrders' => array_slice($orders, 0, 3),
        ]);
    }

    public function updateProfile(): never
    {
        $user = Auth::user();
        $data = [
            'name'             => $this->request->string('name'),
            'email'            => mb_strtolower($this->request->string('email')),
            'phone'            => $this->request->string('phone'),
            'email_password'   => (string) $this->request->input('email_password', ''),
        ];

        $valid = $this->validate([
            'name'  => 'required|min:2|max:100',
            'email' => 'required|email|max:190|unique:users,email,' . (int) $user['id'],
            'phone' => 'required|phone',
        ], User::LABELS, $data);

        // Смена email — чувствительное действие: подтверждаем текущим паролем
        if ($valid['email'] !== $user['email'] && !User::verifyPassword((int) $user['id'], $data['email_password'])) {
            Session::flash('errors', ['email_password' => 'Чтобы сменить email, введите текущий пароль']);
            Session::flashInput($data);
            $this->redirect('/account#profile');
        }

        User::updateProfile((int) $user['id'], $valid['name'], $valid['email'], $valid['phone']);
        Auth::refresh();

        Session::flash('success', 'Профиль сохранён');
        $this->redirect('/account');
    }

    public function updatePassword(): never
    {
        $id = (int) Auth::id();
        $data = [
            'current_password'      => (string) $this->request->input('current_password', ''),
            'password'              => (string) $this->request->input('password', ''),
            'password_confirmation' => (string) $this->request->input('password_confirmation', ''),
        ];

        $valid = $this->validate([
            'current_password' => 'required',
            'password'         => User::PASSWORD_RULES,
        ], User::LABELS, $data, User::PASSWORD_MESSAGES);

        if (!User::verifyPassword($id, $data['current_password'])) {
            Session::flash('errors', ['current_password' => 'Текущий пароль указан неверно']);
            $this->redirect('/account#password');
        }
        if (User::verifyPassword($id, $valid['password'])) {
            Session::flash('errors', ['password' => 'Новый пароль совпадает с текущим']);
            $this->redirect('/account#password');
        }

        User::updatePassword($id, $valid['password']);
        Session::regenerate();

        Session::flash('success', 'Пароль изменён');
        $this->redirect('/account');
    }

    public function orders(): string
    {
        return $this->page('account/orders', 'Мои заказы', 'orders', [
            'orders' => Order::forUser((int) Auth::id()),
        ]);
    }

    public function reviews(): string
    {
        return $this->page('account/reviews', 'Мои отзывы', 'reviews', [
            'reviews' => Review::forUser((int) Auth::id()),
        ]);
    }
}
