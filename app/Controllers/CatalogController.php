<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\View;
use App\Models\Category;
use App\Models\Product;
use App\Services\CatalogQuery;

/**
 * Каталог, страницы категорий и поиск.
 *
 * Одна и та же выдача работает в двух режимах:
 *  - обычный запрос — полная страница (фильтры работают и без JS, как GET-форма);
 *  - AJAX (fetch из catalog.js) — JSON с HTML результатов, без перезагрузки страницы.
 */
final class CatalogController extends Controller
{
    public function index(): string
    {
        $query = CatalogQuery::fromRequest($this->request);
        return $this->render($query, [
            'title'       => 'Каталог',
            'heading'     => 'Каталог',
            'description' => 'Жидкости для аппаратов «Сухой туман», аппараты самообслуживания и комплектующие с доставкой по Беларуси.',
            'breadcrumbs' => [['title' => 'Каталог']],
        ]);
    }

    public function category(string $slug): string
    {
        $category = Category::findBySlug($slug);
        if ($category === null) {
            $this->notFound();
        }

        $query = CatalogQuery::fromRequest($this->request, $category);
        return $this->render($query, [
            'title'       => $category['name'],
            'heading'     => $category['name'],
            'description' => (string) $category['description'],
            'breadcrumbs' => [['title' => 'Каталог', 'url' => url('/catalog')], ['title' => $category['name']]],
        ]);
    }

    public function search(): string
    {
        $query = CatalogQuery::fromRequest($this->request, null, true)->markAsSearch();
        $title = $query->q !== '' ? 'Поиск: «' . $query->q . '»' : 'Поиск';

        return $this->render($query, [
            'title'       => $title,
            'heading'     => $query->q !== '' ? 'Результаты поиска' : 'Поиск по магазину',
            'description' => '',
            'breadcrumbs' => [['title' => 'Поиск']],
            'robots'      => 'noindex, follow',
        ]);
    }

    /** Живые подсказки в шапке: JSON до 6 товаров. */
    public function suggest(): never
    {
        $q = mb_substr(trim((string) $this->request->query('q', '')), 0, 100);
        if (mb_strlen($q) < 2) {
            $this->json(['ok' => true, 'items' => []]);
        }

        $items = array_map(static fn (array $p) => [
            'name'       => $p['name'],
            'url'        => Product::url($p),
            'color'      => $p['aroma_color'] ?: '#C9A96E',
            'category'   => $p['category_name'],
            'price_html' => price($p['price'], $p['old_price'], 'sm', $p['variants_count'] > 1),
        ], Product::suggest($q));

        $this->json(['ok' => true, 'items' => $items]);
    }

    private function render(CatalogQuery $query, array $page): string
    {
        $results = $query->results();
        $facets  = $query->facets();
        $aromaNames = array_column($facets['aromas'], 'name', 'slug');

        $resultsData = [
            'query'   => $query,
            'results' => $results,
            'chips'   => $query->chips($aromaNames),
        ];

        // Режим «без перезагрузки»: только блок результатов
        if ($this->request->isAjax()) {
            $this->json([
                'ok'    => true,
                'html'  => View::partial('catalog/_results', $resultsData),
                'total' => $results['total'],
                'title' => $page['title'] . ' — ' . setting('shop_name', 'Puff Drive'),
                'url'   => $query->url(['page' => $results['page'] > 1 ? $results['page'] : null]),
                'filtersCount' => $query->activeCount(),
            ]);
        }

        return $this->view('catalog/index', $page + $resultsData + [
            'facets'     => $facets,
            'categories' => Category::allWithCounts(),
            'bodyClass'  => 'page-catalog',
            'styles'     => ['catalog.css'],
            'scripts'    => ['catalog.js'],
        ]);
    }
}
