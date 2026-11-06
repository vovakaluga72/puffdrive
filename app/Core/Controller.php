<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Базовый контроллер: рендер шаблонов, JSON, редиректы и валидация.
 */
abstract class Controller
{
    public function __construct(protected Request $request)
    {
    }

    protected function view(string $view, array $data = [], ?string $layout = 'layouts/main'): string
    {
        return View::render($view, $data, $layout);
    }

    protected function json(mixed $data, int $status = 200): never
    {
        Response::json($data, $status);
    }

    protected function redirect(string $path): never
    {
        Response::redirect(str_starts_with($path, 'http') ? $path : url($path));
    }

    protected function back(string $fallback = '/'): never
    {
        Response::back($fallback);
    }

    /**
     * Проверяет данные запроса. При ошибке:
     *  - для AJAX возвращает JSON 422 со списком ошибок;
     *  - для обычной формы — возвращает на форму с ошибками и введёнными данными.
     * При успехе возвращает только проверенные поля.
     */
    protected function validate(array $rules, array $labels = [], ?array $data = null, array $messages = []): array
    {
        $data ??= $this->request->all();
        $validator = Validator::make($data, $rules, $labels, $messages);

        if ($validator->fails()) {
            if ($this->request->wantsJson()) {
                $this->json(['ok' => false, 'errors' => $validator->errors()], 422);
            }
            Session::flash('errors', $validator->errors());
            Session::flashInput($data);
            $this->back();
        }

        return $validator->validated();
    }

    protected function notFound(): never
    {
        throw new HttpException(404);
    }
}
