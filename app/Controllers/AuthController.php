<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Models\User;

/**
 * Вход, регистрация и выход.
 */
final class AuthController extends Controller
{
    private const VIEW_DATA = [
        'bodyClass' => 'page-auth',
        'styles'    => ['account.css'],
        'scripts'   => ['fog.js', 'forms.js'],
    ];

    public function showLogin(): string
    {
        return $this->view('auth/login', self::VIEW_DATA + [
            'title' => 'Вход',
        ]);
    }

    public function login(): never
    {
        $email = mb_strtolower($this->request->string('email'));
        $data  = ['email' => $email, 'password' => (string) $this->request->input('password', '')];

        $this->validate([
            'email'    => 'required|email|max:190',
            'password' => 'required|max:200',
        ], User::LABELS, $data);

        $error = Auth::attempt($email, $data['password'], $this->request->ip());
        if ($error !== null) {
            Session::flash('errors', ['email' => $error]);
            Session::flashInput(['email' => $email]);
            $this->redirect('/login');
        }

        $user = Auth::user();
        Session::flash('success', 'С возвращением, ' . $user['name'] . '!');
        Response::redirect(Auth::intendedUrl('/account'));
    }

    public function showRegister(): string
    {
        return $this->view('auth/register', self::VIEW_DATA + [
            'title' => 'Регистрация',
        ]);
    }

    public function register(): never
    {
        // Ловушка для ботов: скрытое поле, которое человек не заполняет
        if ($this->request->string('website') !== '') {
            $this->redirect('/');
        }

        $data = [
            'name'                  => $this->request->string('name'),
            'email'                 => mb_strtolower($this->request->string('email')),
            'phone'                 => $this->request->string('phone'),
            'password'              => (string) $this->request->input('password', ''),
            'password_confirmation' => (string) $this->request->input('password_confirmation', ''),
            'consent'               => $this->request->input('consent'),
        ];

        $valid = $this->validate([
            'name'     => 'required|min:2|max:100',
            'email'    => 'required|email|max:190|unique:users,email',
            'phone'    => 'required|phone',
            'password' => User::PASSWORD_RULES,
            'consent'  => 'accepted',
        ], User::LABELS, $data, User::PASSWORD_MESSAGES);

        $id = User::create($valid['name'], $valid['email'], $valid['phone'], $valid['password']);
        Auth::login(User::find($id));

        Session::flash('success', 'Добро пожаловать в Puff Drive, ' . $valid['name'] . '! Аккаунт создан.');
        Response::redirect(Auth::intendedUrl('/account'));
    }

    public function logout(): never
    {
        Auth::logout();
        Session::flash('info', 'Вы вышли из аккаунта');
        $this->redirect('/');
    }
}
