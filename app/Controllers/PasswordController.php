<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Models\User;
use App\Services\PasswordResetService;

/**
 * Восстановление пароля: запрос ссылки и установка нового пароля.
 */
final class PasswordController extends Controller
{
    private const VIEW_DATA = [
        'bodyClass' => 'page-auth',
        'styles'    => ['account.css'],
        'scripts'   => ['fog.js', 'forms.js'],
    ];

    public function showForgot(): string
    {
        return $this->view('auth/forgot', self::VIEW_DATA + [
            'title' => 'Восстановление пароля',
            'sent'  => (bool) Session::getFlash('reset_sent', false),
        ]);
    }

    public function sendLink(): never
    {
        $data = ['email' => mb_strtolower($this->request->string('email'))];
        $valid = $this->validate(['email' => 'required|email|max:190'], User::LABELS, $data);

        PasswordResetService::request($valid['email']);

        // Одинаковый ответ для существующих и несуществующих адресов
        Session::flash('reset_sent', true);
        Session::flashInput(['email' => $valid['email']]);
        $this->redirect('/password/forgot');
    }

    public function showReset(string $token): string
    {
        return $this->view('auth/reset', self::VIEW_DATA + [
            'title' => 'Новый пароль',
            'token' => $token,
            'reset' => PasswordResetService::findValid($token),
        ]);
    }

    public function reset(): never
    {
        $token = $this->request->string('token');
        $data = [
            'password'              => (string) $this->request->input('password', ''),
            'password_confirmation' => (string) $this->request->input('password_confirmation', ''),
        ];
        $valid = $this->validate(['password' => User::PASSWORD_RULES], User::LABELS, $data, User::PASSWORD_MESSAGES);

        $user = PasswordResetService::reset($token, $valid['password']);
        if ($user === null) {
            Session::flash('error', 'Ссылка устарела или уже была использована. Запросите новую.');
            $this->redirect('/password/forgot');
        }

        Auth::login($user);
        Session::flash('success', 'Пароль изменён. Вы вошли в аккаунт.');
        $this->redirect('/account');
    }
}
