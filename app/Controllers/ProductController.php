<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Aroma;
use App\Models\Product;
use App\Models\Review;
use App\Services\MachineConfig;

/**
 * Страница товара: галерея, варианты, цена, характеристики, отзывы,
 * похожие товары и — для аппаратов — конфигуратор ароматов.
 */
final class ProductController extends Controller
{
    public function show(string $slug): string
    {
        $product = Product::findActive($slug);
        if ($product === null) {
            $this->notFound();
        }

        $variants = Product::variants((int) $product['id']);
        if (!$variants) {
            $this->notFound(); // товар без продаваемых вариантов не показываем
        }

        // Выбранный вариант: из ?variant=ID, иначе первый в наличии, иначе первый
        $requested = (int) $this->request->query('variant', 0);
        $selected = null;
        foreach ($variants as $variant) {
            if ($variant['id'] === $requested || ($requested === 0 && $selected === null && $variant['stock'] > 0)) {
                $selected = $variant;
            }
        }
        $selected ??= $variants[0];

        $isMachine = $product['category_type'] === 'machine' && $product['aroma_slots'] > 0;
        $aromas = Product::aromas((int) $product['id']);

        Product::incrementViews((int) $product['id']);

        return $this->view('product/show', [
            'title'        => $product['meta_title'] ?: $product['name'],
            'description'  => $product['meta_description'] ?: str_limit((string) ($product['short_description'] ?: $product['description']), 160),
            'product'      => $product,
            'variants'     => $variants,
            'selected'     => $selected,
            'images'       => Product::images((int) $product['id']),
            'aromas'       => $aromas,
            'mainColor'    => $aromas[0]['color'] ?? null,
            'reviews'      => Review::forProduct((int) $product['id']),
            'distribution' => Review::distribution((int) $product['id']),
            'similar'      => Product::similar($product),
            'isMachine'    => $isMachine,
            'allAromas'    => $isMachine ? Aroma::active() : [],
            'fixedButtons' => MachineConfig::FIXED_BUTTONS,
            'breadcrumbs'  => [
                ['title' => 'Каталог', 'url' => url('/catalog')],
                ['title' => $product['category_name'], 'url' => url('/catalog/' . $product['category_slug'])],
                ['title' => $product['name']],
            ],
            'bodyClass'    => 'page-product',
            'styles'       => $isMachine ? ['product.css', 'machine.css'] : ['product.css'],
            'scripts'      => $isMachine ? ['product.js', 'configurator.js'] : ['product.js'],
        ]);
    }
}
