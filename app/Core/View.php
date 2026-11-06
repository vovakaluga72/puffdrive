<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Рендеринг PHP-шаблонов из app/Views.
 *
 * Страница рендерится внутрь layout'а: layout получает готовый HTML
 * в переменной $content. Шаблоны могут добавлять стили и скрипты
 * в стеки (View::push('scripts', ...)), которые layout выводит в нужном месте.
 */
final class View
{
    private static array $shared = [];
    private static array $stacks = [];

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function render(string $view, array $data = [], ?string $layout = 'layouts/main'): string
    {
        $content = self::file($view, $data);

        if ($layout === null) {
            return $content;
        }
        return self::file($layout, array_merge($data, ['content' => $content]));
    }

    /** Вставка частичного шаблона: <?= View::partial('partials/product-card', ['product' => $p]) ?> */
    public static function partial(string $name, array $data = []): string
    {
        return self::file($name, $data);
    }

    public static function push(string $stack, string $html): void
    {
        self::$stacks[$stack][] = $html;
    }

    public static function stack(string $stack): string
    {
        return implode("\n", array_unique(self::$stacks[$stack] ?? []));
    }

    private static function file(string $name, array $data): string
    {
        $name = str_replace(['..', '\\'], ['', '/'], $name);
        $path = BASE_PATH . '/app/Views/' . $name . '.php';

        if (!is_file($path)) {
            throw new \RuntimeException("Шаблон не найден: {$name}");
        }

        $level = ob_get_level();
        ob_start();
        try {
            (static function (string $__path, array $__data): void {
                extract($__data, EXTR_SKIP);
                require $__path;
            })($path, array_merge(self::$shared, $data));
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
