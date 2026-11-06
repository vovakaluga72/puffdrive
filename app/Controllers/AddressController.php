<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Models\Address;
use App\Models\User;

/**
 * Сохранённые адреса доставки в личном кабинете.
 * Каждое действие проверяет, что адрес принадлежит текущему пользователю.
 */
final class AddressController extends Controller
{
    private const RULES = [
        'title'     => 'max:60',
        'recipient' => 'max:100',
        'phone'     => 'phone',
        'city'      => 'required|min:2|max:100',
        'address'   => 'required|min:3|max:255',
        'postcode'  => 'regex:/^\d{6}$/',
    ];

    private const LABELS = [
        'title'     => 'Название',
        'recipient' => 'Получатель',
        'phone'     => 'Телефон',
        'city'      => 'Город',
        'address'   => 'Адрес',
        'postcode'  => 'Индекс',
    ];

    private const MESSAGES = [
        'postcode.regex' => 'Индекс в Беларуси — 6 цифр, например 230025',
    ];

    public function index(): string
    {
        $userId = (int) Auth::id();
        $editId = (int) $this->request->query('edit', 0);
        $edit = $editId > 0 ? Address::findForUser($editId, $userId) : null;

        return $this->view('account/addresses', [
            'title'     => 'Мои адреса',
            'section'   => 'addresses',
            'user'      => Auth::user(),
            'stats'     => User::stats($userId),
            'addresses' => Address::forUser($userId),
            'edit'      => $edit,
            'canAdd'    => Address::countForUser($userId) < Address::MAX_PER_USER,
            'bodyClass' => 'page-account',
            'styles'    => ['account.css'],
            'scripts'   => ['forms.js'],
        ]);
    }

    public function store(): never
    {
        $userId = (int) Auth::id();
        if (Address::countForUser($userId) >= Address::MAX_PER_USER) {
            Session::flash('error', 'Можно сохранить не больше ' . Address::MAX_PER_USER . ' адресов');
            $this->redirect('/account/addresses');
        }

        Address::create($userId, $this->validated());
        Session::flash('success', 'Адрес сохранён');
        $this->redirect('/account/addresses');
    }

    public function update(string $id): never
    {
        $address = $this->findOrFail((int) $id);
        Address::update((int) $address['id'], (int) Auth::id(), $this->validated());
        Session::flash('success', 'Адрес обновлён');
        $this->redirect('/account/addresses');
    }

    public function makeDefault(string $id): never
    {
        $address = $this->findOrFail((int) $id);
        Address::setDefault((int) $address['id'], (int) Auth::id());
        Session::flash('success', 'Основной адрес изменён');
        $this->redirect('/account/addresses');
    }

    public function delete(string $id): never
    {
        $address = $this->findOrFail((int) $id);
        Address::delete((int) $address['id'], (int) Auth::id());
        Session::flash('success', 'Адрес удалён');
        $this->redirect('/account/addresses');
    }

    private function validated(): array
    {
        $data = [
            'title'      => $this->request->string('title'),
            'recipient'  => $this->request->string('recipient'),
            'phone'      => $this->request->string('phone'),
            'city'       => $this->request->string('city'),
            'address'    => $this->request->string('address'),
            'postcode'   => $this->request->string('postcode'),
            'is_default' => $this->request->bool('is_default'),
        ];
        $valid = $this->validate(self::RULES, self::LABELS, $data, self::MESSAGES);
        $valid['is_default'] = $data['is_default'];
        return $valid;
    }

    private function findOrFail(int $id): array
    {
        $address = Address::findForUser($id, (int) Auth::id());
        if ($address === null) {
            $this->notFound();
        }
        return $address;
    }
}
