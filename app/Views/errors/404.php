<?php
/** Страница 404 в стиле сайта. */
?>
<section class="error-page">
    <div class="error-page__fog" aria-hidden="true"></div>
    <div class="container error-page__inner">
        <p class="error-page__code" aria-hidden="true">4<span>0</span>4</p>
        <h1 class="error-page__title">Страница растворилась в тумане</h1>
        <p class="error-page__text">
            Возможно, ссылка устарела или товар больше не продаётся.
            Загляните в каталог — там точно найдётся подходящий аромат.
        </p>
        <div class="error-page__actions">
            <a class="btn btn--primary" href="<?= e(url('/catalog')) ?>">В каталог <?= icon('arrow-right') ?></a>
            <a class="btn btn--ghost" href="<?= e(url('/')) ?>">На главную</a>
        </div>
        <form class="error-page__search" action="<?= e(url('/search')) ?>" method="get" role="search">
            <label class="visually-hidden" for="error-search">Поиск по магазину</label>
            <input class="input" id="error-search" type="search" name="q" placeholder="Например, Морской бриз или форсунка" maxlength="100">
            <button class="btn btn--outline" type="submit"><?= icon('search') ?> Найти</button>
        </form>
    </div>
</section>
