<?php
/**
 * Глобальные функции-хелперы, доступные во всех контроллерах и шаблонах.
 */

declare(strict_types=1);

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Session;
use App\Models\Setting;

// ------------------------------------------------------------------
// Конфигурация, адреса, окружение
// ------------------------------------------------------------------

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

/** Путь сайта от корня домена ('' — сайт в корне, '/shop' — в подпапке). */
function base_path(): string
{
    static $path = null;
    if ($path === null) {
        $path = rtrim((string) parse_url((string) config('app.url', ''), PHP_URL_PATH), '/');
    }
    return $path;
}

/** Адрес внутри сайта: url('/catalog', ['page' => 2]) -> /catalog?page=2 */
function url(string $path = '/', array $query = []): string
{
    $path = '/' . ltrim($path, '/');
    $result = base_path() . ($path === '/' && base_path() !== '' ? '' : $path);
    $result = $result === '' ? '/' : $result;

    $query = array_filter($query, static fn ($v) => $v !== null && $v !== '' && $v !== []);
    return $query ? $result . '?' . http_build_query($query) : $result;
}

/** Полный адрес с доменом — для писем и canonical. */
function absolute_url(string $path = '/'): string
{
    $origin = rtrim((string) preg_replace('#^(https?://[^/]+).*$#', '$1', (string) config('app.url', '')), '/');
    return $origin . url($path);
}

/** Ссылка на статический файл с меткой версии для сброса кэша браузера. */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = BASE_PATH . '/public/assets/' . $path;
    $version = is_file($file) ? (string) filemtime($file) : '1';
    return url('/assets/' . $path) . '?v=' . $version;
}

/** Ссылка на загруженный файл (картинку товара). */
function upload_url(?string $path): ?string
{
    return $path ? url('/uploads/' . ltrim($path, '/')) : null;
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
        || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function current_path(): string
{
    $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $base = base_path();
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
    }
    return '/' . trim($path, '/');
}

/** Активный пункт меню: is_active_path('/catalog') */
function is_active_path(string $prefix): bool
{
    $path = current_path();
    return $prefix === '/' ? $path === '/' : ($path === $prefix || str_starts_with($path, rtrim($prefix, '/') . '/'));
}

/** Грубое определение поисковых роботов — им не показываем приветственный экран. */
function is_bot(string $userAgent): bool
{
    return $userAgent === ''
        || (bool) preg_match('/bot|crawl|spider|slurp|yandex|google|bing|baidu|duckduck|facebookexternalhit|telegrambot|whatsapp|preview|lighthouse|headless/i', $userAgent);
}

// ------------------------------------------------------------------
// Безопасность и формы
// ------------------------------------------------------------------

/** Экранирование вывода — защита от XSS. Используется во всех шаблонах. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return Csrf::field();
}

/** Значение поля, введённое пользователем до ошибки валидации. */
function old(string $key, mixed $default = ''): mixed
{
    return Session::old($key, $default);
}

/** Текст ошибки валидации для поля (или null). */
function error(string $key): ?string
{
    $errors = Session::getFlash('errors', []);
    return $errors[$key] ?? null;
}

function flash(string $key, mixed $default = null): mixed
{
    return Session::getFlash($key, $default);
}

/** Значение из таблицы настроек магазина (редактируется в админке). */
function setting(string $key, string $default = ''): string
{
    return Setting::get($key, $default);
}

/** Безопасная вставка данных в data-атрибут как JSON. */
function json_attr(mixed $data): string
{
    return e(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

// ------------------------------------------------------------------
// Цены (единый формат для всего сайта — см. компонент .price в CSS)
// ------------------------------------------------------------------

/** Знак белорусского рубля: «Б» с горизонтальной чертой, масштабируется вместе с текстом. */
function byn_sign(): string
{
    return '<svg class="price__cur" viewBox="0 0 14 20" aria-hidden="true" focusable="false">'
         . '<path d="M11.8 2.6H4.2v14.8h4.4a3.8 3.8 0 0 0 0-7.6H4.2" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linejoin="miter"/>'
         // горизонтальная черта через середину буквы, выходящая влево за стойку
         . '<path d="M0.4 9.8h4.8" fill="none" stroke="currentColor" stroke-width="2"/>'
         . '</svg>';
}

/** Процент скидки: round((старая − новая) / старая × 100). */
function discount_percent(float $old, float $new): int
{
    if ($old <= 0 || $new >= $old) {
        return 0;
    }
    return (int) round(($old - $new) / $old * 100);
}

/** Одна цена: <span class="price">2 299<small>.00</small> Б</span> */
function price_tag(float $amount, string $modifier = ''): string
{
    $formatted = number_format($amount, 2, '.', '');
    [$int, $dec] = explode('.', $formatted);
    $intFormatted = number_format((float) $int, 0, '', "\u{00A0}");
    $label = number_format($amount, 2, ',', ' ') . ' белорусских рублей';

    return '<span class="price' . ($modifier !== '' ? ' ' . e($modifier) : '') . '" aria-label="' . e($label) . '">'
         . '<span class="price__int" aria-hidden="true">' . $intFormatted . '</span>'
         . '<span class="price__dec" aria-hidden="true">.' . $dec . '</span>'
         . byn_sign()
         . '</span>';
}

/**
 * Блок цены. Если передана старая цена больше новой — двухстрочный блок со скидкой:
 *   2 299.00 Б
 *   3 399.00 Б  −32%
 *
 * @param string $size  sm | md | lg | xl — размер шрифта
 * @param bool   $from  добавить «от» перед ценой (у товаров с вариантами)
 */
function price(float $amount, ?float $old = null, string $size = 'md', bool $from = false): string
{
    $prefix = $from ? '<span class="price-block__from">от</span>' : '';
    $main   = '<div class="price-block__main">' . $prefix . price_tag($amount) . '</div>';

    if ($old !== null && $old > $amount) {
        $percent = discount_percent($old, $amount);
        return '<div class="price-block price-block--' . e($size) . ' price-block--sale">'
             . $main
             . '<div class="price-block__old">' . price_tag($old, 'price--old')
             . '<span class="price-block__pct">−' . $percent . '%</span></div>'
             . '</div>';
    }

    return '<div class="price-block price-block--' . e($size) . '">' . $main . '</div>';
}

/** Текстовая цена для писем, логов и атрибутов: «2 299.00 BYN». */
function money(float $amount): string
{
    return number_format($amount, 2, '.', "\u{00A0}") . "\u{00A0}BYN";
}

// ------------------------------------------------------------------
// Текст и форматирование
// ------------------------------------------------------------------

/** Склонение: plural(5, ['товар', 'товара', 'товаров']) -> «товаров» */
function plural(int $n, array $forms): string
{
    $n = abs($n) % 100;
    $n1 = $n % 10;
    if ($n > 10 && $n < 20) {
        return $forms[2];
    }
    if ($n1 > 1 && $n1 < 5) {
        return $forms[1];
    }
    if ($n1 === 1) {
        return $forms[0];
    }
    return $forms[2];
}

function str_limit(string $text, int $limit = 120): string
{
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
    return mb_strlen($text) > $limit ? rtrim(mb_substr($text, 0, $limit - 1)) . '…' : $text;
}

/** Абзацы из многострочного текста (с экранированием). */
function nl2p(string $text): string
{
    $parts = preg_split('/\R{2,}/u', trim($text)) ?: [];
    return implode('', array_map(static fn ($p) => '<p>' . nl2br(e(trim($p))) . '</p>', $parts));
}

/**
 * Нормализует белорусский номер к виду +375XXXXXXXXX.
 * Принимает «+375 (29) 123-45-67», «80291234567», «375291234567».
 * Возвращает null, если номер некорректен.
 */
function normalize_phone(string $phone): ?string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (str_starts_with($digits, '80') && strlen($digits) === 11) {
        $digits = '375' . substr($digits, 2);
    }
    if (!preg_match('/^375(25|29|33|44|1[5-7]|2[1-3])\d{7}$/', $digits)) {
        return null;
    }
    return '+' . $digits;
}

/** +375291234567 -> +375 (29) 123-45-67 */
function format_phone(?string $phone): string
{
    $normalized = normalize_phone((string) $phone);
    if ($normalized === null) {
        return (string) $phone;
    }
    $d = substr($normalized, 4);
    return sprintf('+375 (%s) %s-%s-%s', substr($d, 0, 2), substr($d, 2, 3), substr($d, 5, 2), substr($d, 7, 2));
}

function tel_href(?string $phone): string
{
    return 'tel:' . (normalize_phone((string) $phone) ?? preg_replace('/[^\d+]/', '', (string) $phone));
}

function format_date(?string $date, bool $withTime = false): string
{
    if (!$date) {
        return '';
    }
    $months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
    $ts = strtotime($date);
    $result = date('j', $ts) . ' ' . $months[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
    return $withTime ? $result . ', ' . date('H:i', $ts) : $result;
}

// ------------------------------------------------------------------
// Графика: иконки, плейсхолдеры, рейтинг
// ------------------------------------------------------------------

/** Inline-SVG иконка из набора app/Views/partials/icons.php */
function icon(string $name, string $class = ''): string
{
    static $icons = null;
    $icons ??= require BASE_PATH . '/app/Views/partials/icons.php';

    if (!isset($icons[$name])) {
        return '';
    }
    $icon = $icons[$name];
    $attrs = ($icon['fill'] ?? false)
        ? 'fill="currentColor"'
        : 'fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"';

    return '<svg class="icon' . ($class !== '' ? ' ' . e($class) : '') . '" viewBox="0 0 24 24" ' . $attrs
         . ' aria-hidden="true" focusable="false">' . $icon['svg'] . '</svg>';
}

/**
 * SVG-заглушка товара в стиле сайта (пока нет фотографий).
 * $type: liquid | machine | part. $color — цвет аромата (для жидкостей).
 */
function placeholder_svg(string $type, ?string $color = null, string $label = '', int $slots = 0): string
{
    static $n = 0;
    $id = 'ph' . (++$n);
    $color = preg_match('/^#[0-9a-f]{6}$/i', (string) $color) ? $color : '#c9a96e';

    $shape = match ($type) {
        'liquid' =>
            // Канистра с ручкой и цветной этикеткой аромата
            '<path d="M118 70h44v16h-44z" fill="#2a2a30"/>'
          . '<rect x="112" y="60" width="56" height="14" rx="5" fill="#34343b"/>'
          . '<path d="M86 104c0-10 8-18 18-18h72c10 0 18 8 18 18v138c0 12-10 22-22 22H108c-12 0-22-10-22-22z" fill="url(#' . $id . 'b)" stroke="rgba(255,255,255,.08)"/>'
          . '<path d="M100 104c0-4 3-7 7-7h12" stroke="rgba(255,255,255,.22)" stroke-width="3" stroke-linecap="round" fill="none"/>'
          . '<rect x="102" y="148" width="76" height="70" rx="10" fill="' . $color . '" opacity=".92"/>'
          . '<rect x="102" y="148" width="76" height="70" rx="10" fill="url(#' . $id . 'g)"/>'
          . '<text x="140" y="178" text-anchor="middle" font-family="Manrope,Arial,sans-serif" font-size="11" font-weight="800" letter-spacing="2" fill="#0b0b0d">PUFF</text>'
          . '<text x="140" y="194" text-anchor="middle" font-family="Manrope,Arial,sans-serif" font-size="11" font-weight="800" letter-spacing="2" fill="#0b0b0d">DRIVE</text>'
          . '<circle cx="140" cy="206" r="2" fill="#0b0b0d"/>',
        'machine' =>
            // Стойка аппарата: табло, две колонки кнопок, держатель
            '<rect x="96" y="40" width="88" height="232" rx="14" fill="url(#' . $id . 'b)" stroke="rgba(255,255,255,.1)"/>'
          . '<rect x="106" y="52" width="68" height="20" rx="4" fill="#070708" stroke="' . $color . '" stroke-opacity=".5"/>'
          . '<text x="140" y="66" text-anchor="middle" font-family="monospace" font-size="9" fill="' . $color . '">PUFF·DRIVE</text>'
          . implode('', array_map(static fn ($i) =>
                '<rect x="108" y="' . (84 + $i * 20) . '" width="28" height="12" rx="6" fill="#1c1c21" stroke="rgba(255,255,255,.12)"/>'
              . '<circle cx="114" cy="' . (90 + $i * 20) . '" r="2" fill="' . $color . '"/>', range(0, ($slots > 0 ? $slots : 6) - 1)))
          . implode('', array_map(static fn ($i) =>
                '<rect x="144" y="' . (84 + $i * 20) . '" width="28" height="12" rx="6" fill="#1c1c21" stroke="rgba(255,255,255,.12)"/>'
              . '<circle cx="150" cy="' . (90 + $i * 20) . '" r="2" fill="#e5484d" opacity=".8"/>', range(0, 2)))
          . '<rect x="144" y="148" width="28" height="28" rx="4" fill="#f3efe8" opacity=".85"/>'
          . '<path d="M148 152h6v6h-6zM162 152h6v6h-6zM148 166h6v6h-6zM160 164h3v3h-3zM164 168h4v4h-4z" fill="#0b0b0d"/>'
          . '<rect x="108" y="212" width="64" height="8" rx="4" fill="#0e0e10"/>'
          . '<path d="M184 120c22 4 26 30 14 52" stroke="#2c2c33" stroke-width="5" fill="none" stroke-linecap="round"/>'
          . '<rect x="190" y="168" width="14" height="30" rx="6" fill="#34343b"/>',
        default =>
            // Комплектующая: шестерня с форсункой
            '<circle cx="140" cy="150" r="58" fill="url(#' . $id . 'b)" stroke="rgba(255,255,255,.08)"/>'
          . '<path d="M140 104l8 14 16-4 2 16 15 7-8 14 8 14-15 7-2 16-16-4-8 14-8-14-16 4-2-16-15-7 8-14-8-14 15-7 2-16 16 4z" fill="none" stroke="' . $color . '" stroke-width="3" stroke-linejoin="round" opacity=".9"/>'
          . '<circle cx="140" cy="150" r="16" fill="#0b0b0d" stroke="' . $color . '" stroke-width="3"/>'
          . '<circle cx="140" cy="150" r="5" fill="' . $color . '"/>',
    };

    $caption = $label !== ''
        ? '<text x="140" y="300" text-anchor="middle" font-family="Manrope,Arial,sans-serif" font-size="12" fill="rgba(243,239,232,.4)" letter-spacing="1">' . e(mb_strtoupper(str_limit($label, 28))) . '</text>'
        : '';

    return '<svg class="placeholder" viewBox="0 0 280 320" role="img" aria-label="' . e($label !== '' ? $label : 'Изображение товара') . '" preserveAspectRatio="xMidYMid slice">'
         . '<defs>'
         . '<radialGradient id="' . $id . 'r" cx="50%" cy="38%" r="70%"><stop offset="0" stop-color="' . $color . '" stop-opacity=".22"/><stop offset="1" stop-color="#0b0b0d" stop-opacity="0"/></radialGradient>'
         . '<linearGradient id="' . $id . 'b" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#26262c"/><stop offset="1" stop-color="#121215"/></linearGradient>'
         . '<linearGradient id="' . $id . 'g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".35"/><stop offset=".5" stop-color="#fff" stop-opacity="0"/></linearGradient>'
         . '</defs>'
         . '<rect width="280" height="320" fill="#141417"/>'
         . '<rect width="280" height="320" fill="url(#' . $id . 'r)"/>'
         . '<ellipse cx="140" cy="284" rx="78" ry="8" fill="#000" opacity=".45"/>'
         . $shape
         . $caption
         . '</svg>';
}

/** Звёзды рейтинга (с половинками) для вывода в карточке и отзывах. */
function rating_stars(float $rating, string $class = ''): string
{
    $rating = max(0, min(5, $rating));
    $percent = $rating / 5 * 100;
    return '<span class="stars' . ($class !== '' ? ' ' . e($class) : '') . '" role="img" aria-label="Рейтинг ' . number_format($rating, 1, ',', '') . ' из 5">'
         . '<span class="stars__base">★★★★★</span>'
         . '<span class="stars__fill" style="width:' . round($percent, 1) . '%">★★★★★</span>'
         . '</span>';
}

/** Количество товаров в корзине для бейджа в шапке. */
function cart_count(): int
{
    return \App\Services\CartService::count();
}
