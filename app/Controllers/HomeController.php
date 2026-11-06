<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Aroma;
use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Models\Setting;

final class HomeController extends Controller
{
    /** Имя cookie «приветственный экран уже показан» (ставится из splash.js на 30 дней). */
    public const SPLASH_COOKIE = 'pd_splash_seen';

    public function index(): string
    {
        $showSplash = $this->shouldShowSplash();

        return $this->view('home/index', [
            'title'       => 'Жидкости и аппараты «Сухой туман»',
            'description' => setting('meta_description'),
            'bodyClass'   => 'page-home',
            'htmlClass'   => $showSplash ? 'has-splash' : '',
            'styles'      => ['home.css', 'machine.css', 'splash.css'],
            'scripts'     => ['fog.js', 'splash.js', 'home.js'],
            'showSplash'  => $showSplash,
            'splashLines' => array_filter(array_map('trim', preg_split('/\R/u', setting('splash_text')) ?: [])),
            'socials'     => Setting::socials(),
            'categories'  => Category::allWithCounts(),
            'popular'     => Product::popular(8),
            'reviews'     => Review::latestApproved(6),
            'aromaCount'  => Aroma::countActive(),
            'heroSlots'   => $this->heroSlots(),
        ]);
    }

    /** 6 ароматов для декоративной панели аппарата в hero: сначала популярные. */
    private function heroSlots(): array
    {
        $aromas = Aroma::active();
        usort($aromas, static fn ($a, $b) => (int) $b['is_popular'] <=> (int) $a['is_popular']);
        return array_slice($aromas, 0, 6);
    }

    /**
     * Приветственный экран показывается только на главной, только людям
     * (не поисковым роботам) и только если его ещё не видели за последние 30 дней.
     * Ссылка из футера (?splash=1) показывает его принудительно.
     */
    private function shouldShowSplash(): bool
    {
        if (setting('splash_enabled', '1') !== '1') {
            return false;
        }
        if ($this->request->query('splash') === '1') {
            return true;
        }
        if (is_bot($this->request->userAgent())) {
            return false;
        }
        return $this->request->cookie(self::SPLASH_COOKIE) === null;
    }
}
