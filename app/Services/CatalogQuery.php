<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Request;
use App\Models\Product;

/**
 * Запрос каталога: фильтры, сортировка, пагинация и поиск.
 *
 * Параметры берутся из адресной строки, поэтому любую выдачу можно
 * сохранить в закладки или отправить ссылкой:
 *   /catalog/zhidkosti?volume[]=5000&aroma[]=ocean&price_max=150&sort=price_asc&page=2
 *
 * Фильтры по вариантам (объём, цена, наличие, скидка) применяются вместе:
 * товар попадает в выдачу, если у него есть хотя бы ОДИН вариант,
 * подходящий под все условия сразу (например, «5 л дешевле 150 BYN»).
 */
final class CatalogQuery
{
    public const PER_PAGE = 12;

    public const SORTS = [
        'popular'    => 'По популярности',
        'price_asc'  => 'Сначала дешевле',
        'price_desc' => 'Сначала дороже',
        'new'        => 'Новинки',
        'rating'     => 'По рейтингу',
    ];

    public const VOLUMES = [500 => '0,5 л', 1000 => '1 л', 5000 => '5 л'];

    private function __construct(
        public readonly ?array $category,
        public readonly string $q,
        public readonly ?float $priceMin,
        public readonly ?float $priceMax,
        public readonly array $volumes,
        public readonly array $aromas,
        public readonly bool $inStock,
        public readonly bool $sale,
        public readonly string $sort,
        public readonly int $page,
    ) {
    }

    /** Разбор и нормализация параметров запроса (всё лишнее отбрасывается). */
    public static function fromRequest(Request $request, ?array $category = null, bool $isSearch = false): self
    {
        $q = $isSearch ? mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $request->query('q', '')) ?? ''), 0, 100) : '';

        $min = self::money($request->query('price_min'));
        $max = self::money($request->query('price_max'));
        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        $volumes = array_values(array_unique(array_filter(
            array_map('intval', (array) $request->query('volume', [])),
            static fn (int $v) => isset(self::VOLUMES[$v])
        )));
        sort($volumes);

        $aromas = array_values(array_unique(array_filter(
            array_map(static fn ($s) => is_string($s) ? strtolower($s) : '', (array) $request->query('aroma', [])),
            static fn (string $s) => (bool) preg_match('/^[a-z0-9-]{1,80}$/', $s)
        )));
        $aromas = array_slice($aromas, 0, 20);

        $sort = (string) $request->query('sort', '');
        if (!isset(self::SORTS[$sort])) {
            $sort = $q !== '' ? 'relevance' : 'popular';
        }

        return new self(
            category: $category,
            q: $q,
            priceMin: $min,
            priceMax: $max,
            volumes: $volumes,
            aromas: $aromas,
            inStock: $request->query('in_stock') === '1',
            sale: $request->query('sale') === '1',
            sort: $sort,
            page: max(1, min(1000, (int) $request->query('page', 1))),
        );
    }

    /**
     * Товары текущей страницы и общее количество.
     *
     * @return array{items: array, total: int, page: int, pages: int}
     */
    public function results(): array
    {
        [$where, $params] = $this->where();

        $total = (int) Database::fetchColumn(
            'SELECT COUNT(*) FROM (' . Product::cardSelect() . $where . ') AS x',
            $params
        );
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page  = min($this->page, $pages);

        [$order, $orderParams] = $this->orderBy();
        $items = Database::fetchAll(
            Product::cardSelect() . $where . ' ORDER BY ' . $order . ' LIMIT :limit OFFSET :offset',
            $params + $orderParams + ['limit' => self::PER_PAGE, 'offset' => ($page - 1) * self::PER_PAGE]
        );

        return [
            'items' => Product::cast($items),
            'total' => $total,
            'page'  => $page,
            'pages' => $pages,
        ];
    }

    /** Значения для панели фильтров в пределах текущей категории. */
    public function facets(): array
    {
        $catSql = $this->category ? ' AND p.category_id = :cat' : '';
        $catParams = $this->category ? ['cat' => (int) $this->category['id']] : [];

        $bounds = Database::fetch(
            'SELECT MIN(' . Product::EFFECTIVE_PRICE . ') AS min_price, MAX(' . Product::EFFECTIVE_PRICE . ') AS max_price
               FROM product_variants v
               JOIN products p ON p.id = v.product_id AND p.is_active = 1
              WHERE v.is_active = 1' . $catSql,
            $catParams
        ) ?? [];

        $volumes = array_map('intval', array_column(Database::fetchAll(
            'SELECT DISTINCT v.volume_ml
               FROM product_variants v
               JOIN products p ON p.id = v.product_id AND p.is_active = 1
              WHERE v.is_active = 1 AND v.volume_ml IS NOT NULL' . $catSql . '
              ORDER BY v.volume_ml',
            $catParams
        ), 'volume_ml'));

        $aromas = Database::fetchAll(
            'SELECT a.slug, a.name, a.color, COUNT(DISTINCT p.id) AS products_count
               FROM aromas a
               JOIN product_aromas pa ON pa.aroma_id = a.id
               JOIN products p ON p.id = pa.product_id AND p.is_active = 1
              WHERE a.is_active = 1' . $catSql . '
              GROUP BY a.id, a.slug, a.name, a.color, a.sort_order
              ORDER BY a.sort_order, a.name',
            $catParams
        );

        return [
            'price_min' => isset($bounds['min_price']) ? floor((float) $bounds['min_price']) : 0,
            'price_max' => isset($bounds['max_price']) ? ceil((float) $bounds['max_price']) : 0,
            'volumes'   => array_values(array_filter($volumes, static fn ($v) => isset(self::VOLUMES[$v]))),
            'aromas'    => $aromas,
        ];
    }

    /** Есть ли хоть один фильтр (кроме сортировки и страницы). */
    public function hasFilters(): bool
    {
        return $this->priceMin !== null || $this->priceMax !== null || $this->volumes || $this->aromas
            || $this->inStock || $this->sale;
    }

    public function activeCount(): int
    {
        return ($this->priceMin !== null || $this->priceMax !== null ? 1 : 0)
            + count($this->volumes) + count($this->aromas) + (int) $this->inStock + (int) $this->sale;
    }

    /** Параметры URL текущей выдачи; $override позволяет поменять/убрать отдельные значения. */
    public function params(array $override = []): array
    {
        $params = [
            'q'         => $this->q !== '' ? $this->q : null,
            'price_min' => $this->priceMin,
            'price_max' => $this->priceMax,
            'volume'    => $this->volumes ?: null,
            'aroma'     => $this->aromas ?: null,
            'in_stock'  => $this->inStock ? '1' : null,
            'sale'      => $this->sale ? '1' : null,
            'sort'      => in_array($this->sort, ['popular', 'relevance'], true) ? null : $this->sort,
            'page'      => $this->page > 1 ? $this->page : null,
        ];
        foreach ($override as $key => $value) {
            $params[$key] = $value;
        }
        return array_filter($params, static fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /** Адрес страницы выдачи (каталог, категория или поиск) с параметрами. */
    public function url(array $override = []): string
    {
        $base = $this->category ? '/catalog/' . $this->category['slug'] : ($this->q !== '' || $this->isSearchPage() ? '/search' : '/catalog');
        return url($base, $this->params($override));
    }

    /**
     * «Чипсы» активных фильтров со ссылками на их отмену.
     *
     * @return array<array{label: string, url: string}>
     */
    public function chips(array $aromaNames): array
    {
        $chips = [];
        if ($this->priceMin !== null || $this->priceMax !== null) {
            $label = 'Цена: ' . ($this->priceMin !== null ? 'от ' . self::fmt($this->priceMin) . ' ' : '')
                   . ($this->priceMax !== null ? 'до ' . self::fmt($this->priceMax) : '') . ' BYN';
            $chips[] = ['label' => $label, 'url' => $this->url(['price_min' => null, 'price_max' => null, 'page' => null])];
        }
        foreach ($this->volumes as $volume) {
            $chips[] = [
                'label' => 'Объём ' . self::VOLUMES[$volume],
                'url'   => $this->url(['volume' => array_values(array_diff($this->volumes, [$volume])), 'page' => null]),
            ];
        }
        foreach ($this->aromas as $slug) {
            $chips[] = [
                'label' => $aromaNames[$slug] ?? $slug,
                'url'   => $this->url(['aroma' => array_values(array_diff($this->aromas, [$slug])), 'page' => null]),
            ];
        }
        if ($this->inStock) {
            $chips[] = ['label' => 'В наличии', 'url' => $this->url(['in_stock' => null, 'page' => null])];
        }
        if ($this->sale) {
            $chips[] = ['label' => 'Со скидкой', 'url' => $this->url(['sale' => null, 'page' => null])];
        }
        return $chips;
    }

    private bool $searchPage = false;

    public function markAsSearch(): self
    {
        $this->searchPage = true;
        return $this;
    }

    public function isSearchPage(): bool
    {
        return $this->searchPage;
    }

    // ------------------------------------------------------------------
    // Построение SQL
    // ------------------------------------------------------------------

    /** @return array{0: string, 1: array} фрагмент « AND …» и параметры */
    private function where(): array
    {
        $conditions = [];
        $params = [];

        if ($this->category) {
            $conditions[] = 'p.category_id = :cat';
            $params['cat'] = (int) $this->category['id'];
        }

        // Поиск: каждое слово должно встретиться в названии, описании, категории или аромате
        if ($this->q !== '') {
            foreach (array_slice(explode(' ', $this->q), 0, 5) as $i => $word) {
                $like = '%' . addcslashes($word, '%_\\') . '%';
                $conditions[] = "(p.name LIKE :qn{$i} OR p.short_description LIKE :qs{$i} OR p.description LIKE :qd{$i}
                    OR c.name LIKE :qc{$i}
                    OR EXISTS (SELECT 1 FROM product_aromas pas JOIN aromas aas ON aas.id = pas.aroma_id
                                WHERE pas.product_id = p.id AND aas.name LIKE :qa{$i})
                    OR EXISTS (SELECT 1 FROM product_variants vs WHERE vs.product_id = p.id AND vs.sku LIKE :qk{$i}))";
                foreach (['qn', 'qs', 'qd', 'qc', 'qa', 'qk'] as $prefix) {
                    $params[$prefix . $i] = $like;
                }
            }
        }

        // Условия по вариантам — в одном EXISTS, чтобы они относились к одному варианту
        $variant = [];
        if ($this->volumes) {
            $placeholders = [];
            foreach ($this->volumes as $i => $volume) {
                $placeholders[] = ':vol' . $i;
                $params['vol' . $i] = $volume;
            }
            $variant[] = 'v.volume_ml IN (' . implode(', ', $placeholders) . ')';
        }
        if ($this->priceMin !== null) {
            $variant[] = Product::EFFECTIVE_PRICE . ' >= :pmin';
            $params['pmin'] = $this->priceMin;
        }
        if ($this->priceMax !== null) {
            $variant[] = Product::EFFECTIVE_PRICE . ' <= :pmax';
            $params['pmax'] = $this->priceMax;
        }
        if ($this->inStock) {
            $variant[] = 'v.stock > 0';
        }
        if ($this->sale) {
            $variant[] = Product::SALE_ACTIVE;
        }
        if ($variant) {
            $conditions[] = 'EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.is_active = 1 AND '
                          . implode(' AND ', $variant) . ')';
        }

        if ($this->aromas) {
            $placeholders = [];
            foreach ($this->aromas as $i => $slug) {
                $placeholders[] = ':ar' . $i;
                $params['ar' . $i] = $slug;
            }
            $conditions[] = 'EXISTS (SELECT 1 FROM product_aromas paf JOIN aromas af ON af.id = paf.aroma_id
                                      WHERE paf.product_id = p.id AND af.slug IN (' . implode(', ', $placeholders) . '))';
        }

        return [$conditions ? ' AND ' . implode(' AND ', $conditions) : '', $params];
    }

    /** @return array{0: string, 1: array} */
    private function orderBy(): array
    {
        return match ($this->sort) {
            'price_asc'  => ['price ASC, p.sales_count DESC, p.id', []],
            'price_desc' => ['price DESC, p.sales_count DESC, p.id', []],
            'new'        => ['p.created_at DESC, p.id DESC', []],
            'rating'     => ['p.rating_avg DESC, p.rating_count DESC, p.sales_count DESC', []],
            // Совпадение в названии — выше совпадения в описании
            'relevance'  => [
                'CASE WHEN p.name LIKE :rel THEN 0 ELSE 1 END, p.is_featured DESC, p.sales_count DESC, p.id',
                ['rel' => '%' . addcslashes($this->q, '%_\\') . '%'],
            ],
            default      => ['p.is_featured DESC, p.sales_count DESC, p.id', []],
        };
    }

    private static function money(mixed $value): ?float
    {
        if (!is_scalar($value) || trim((string) $value) === '') {
            return null;
        }
        $number = str_replace([',', ' '], ['.', ''], (string) $value);
        if (!is_numeric($number)) {
            return null;
        }
        return max(0.0, min(100000.0, round((float) $number, 2)));
    }

    private static function fmt(float $value): string
    {
        return fmod($value, 1.0) === 0.0 ? number_format($value, 0, '', ' ') : number_format($value, 2, '.', ' ');
    }
}
