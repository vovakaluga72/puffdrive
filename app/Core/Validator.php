<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Серверная валидация форм.
 *
 *   $v = Validator::make($data, [
 *       'email'    => 'required|email|max:190|unique:users,email',
 *       'phone'    => 'required|phone',
 *       'password' => 'required|min:8|confirmed',
 *   ], ['email' => 'Email']);
 *
 * Правила: required, nullable, string, email, phone, min:N, max:N, between:A,B,
 * numeric, integer, in:a,b,c, confirmed, accepted, regex:/.../, date, unique:table,column[,exceptId],
 * exists:table,column.
 *
 * Свои тексты ошибок: четвёртый аргумент ['password.regex' => 'Пароль должен содержать буквы и цифры'].
 */
final class Validator
{
    private array $errors = [];
    private array $validated = [];

    private function __construct(private array $data, private array $rules, private array $labels, private array $messages = [])
    {
        $this->run();
    }

    public static function make(array $data, array $rules, array $labels = [], array $messages = []): self
    {
        return new self($data, $rules, $labels, $messages);
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** Ошибки: ['email' => 'Текст первой ошибки', ...] */
    public function errors(): array
    {
        return $this->errors;
    }

    public function validated(): array
    {
        return $this->validated;
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleSet) {
            $rules = is_array($ruleSet) ? $ruleSet : explode('|', $ruleSet);
            $value = $this->data[$field] ?? null;
            if (is_string($value)) {
                $value = trim($value);
            }

            $isEmpty = $value === null || $value === '' || $value === [];
            if ($isEmpty && !in_array('required', $rules, true) && !in_array('accepted', $rules, true)) {
                // Необязательное пустое поле: остальные правила не проверяем
                $this->validated[$field] = in_array('nullable', $rules, true) ? null : ($value ?? '');
                continue;
            }

            foreach ($rules as $rule) {
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                $error = $this->check($field, $name, $param, $value);
                if ($error !== null) {
                    $this->errors[$field] = $this->messages[$field . '.' . $name] ?? $error;
                    break;
                }
            }

            if (!isset($this->errors[$field])) {
                // Телефон сохраняем в едином формате +375XXXXXXXXX
                $this->validated[$field] = in_array('phone', $rules, true) ? normalize_phone((string) $value) : $value;
            }
        }
    }

    private function check(string $field, string $rule, ?string $param, mixed $value): ?string
    {
        $label = $this->labels[$field] ?? $field;
        $len   = is_string($value) ? mb_strlen($value) : 0;

        return match ($rule) {
            'required' => ($value === null || $value === '' || $value === [])
                ? "Заполните поле «{$label}»" : null,

            'nullable', 'string' => null,

            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) === false
                ? 'Введите корректный email' : null,

            'phone' => normalize_phone((string) $value) === null
                ? 'Введите номер в формате +375 (XX) XXX-XX-XX' : null,

            'min' => (is_numeric($value) && !is_string($value) ? $value < (float) $param : $len < (int) $param)
                ? "«{$label}»: минимум {$param} " . plural((int) $param, ['символ', 'символа', 'символов']) : null,

            'max' => (is_numeric($value) && !is_string($value) ? $value > (float) $param : $len > (int) $param)
                ? "«{$label}»: максимум {$param} " . plural((int) $param, ['символ', 'символа', 'символов']) : null,

            'between' => $this->between($value, (string) $param)
                ? null : "«{$label}»: значение должно быть от " . str_replace(',', ' до ', (string) $param),

            'numeric' => is_numeric(str_replace(',', '.', (string) $value))
                ? null : "«{$label}» должно быть числом",

            'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false
                ? null : "«{$label}» должно быть целым числом",

            'in' => in_array((string) $value, explode(',', (string) $param), true)
                ? null : "Выберите значение для «{$label}»",

            'confirmed' => ($value === ($this->data[$field . '_confirmation'] ?? null))
                ? null : 'Пароли не совпадают',

            'accepted' => in_array($value, ['1', 1, 'on', 'yes', true, 'true'], true)
                ? null : ($field === 'consent' ? 'Необходимо согласие на обработку персональных данных' : "Подтвердите «{$label}»"),

            'regex' => preg_match((string) $param, (string) $value)
                ? null : "Неверный формат поля «{$label}»",

            'date' => strtotime((string) $value) !== false
                ? null : "Введите корректную дату в поле «{$label}»",

            'unique' => $this->unique((string) $param, $value)
                ? null : ($field === 'email' ? 'Этот email уже зарегистрирован' : "Такое значение «{$label}» уже существует"),

            'exists' => $this->exists((string) $param, $value)
                ? null : "Выбрано несуществующее значение «{$label}»",

            default => throw new \InvalidArgumentException("Неизвестное правило валидации: {$rule}"),
        };
    }

    private function between(mixed $value, string $param): bool
    {
        [$min, $max] = array_map('floatval', explode(',', $param));
        $number = is_numeric($value) ? (float) $value : (float) mb_strlen((string) $value);
        return $number >= $min && $number <= $max;
    }

    private function unique(string $param, mixed $value): bool
    {
        [$table, $column, $except] = array_pad(explode(',', $param), 3, null);
        $this->assertIdentifier($table);
        $this->assertIdentifier($column);

        $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?";
        $params = [$value];
        if ($except !== null && $except !== '') {
            $sql .= ' AND id <> ?';
            $params[] = (int) $except;
        }
        return (int) Database::fetchColumn($sql, $params) === 0;
    }

    private function exists(string $param, mixed $value): bool
    {
        [$table, $column] = array_pad(explode(',', $param), 2, 'id');
        $this->assertIdentifier($table);
        $this->assertIdentifier($column);
        return (int) Database::fetchColumn("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?", [$value]) > 0;
    }

    private function assertIdentifier(?string $name): void
    {
        if ($name === null || !preg_match('/^[a-z_][a-z0-9_]*$/i', $name)) {
            throw new \InvalidArgumentException('Недопустимое имя таблицы/колонки в правиле валидации');
        }
    }
}
