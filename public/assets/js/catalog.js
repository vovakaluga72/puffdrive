/* ==================================================================
   Каталог: фильтрация, сортировка и пагинация без перезагрузки.

   Форма фильтров — обычная GET-форма (работает и без JS). Скрипт
   перехватывает изменения, запрашивает тот же адрес через fetch
   (сервер отвечает JSON с готовым HTML выдачи) и обновляет URL через
   history.pushState — ссылку на отфильтрованную выдачу можно скопировать,
   а кнопки «назад/вперёд» браузера работают как обычно.
   ================================================================== */
(() => {
    'use strict';

    const form = document.querySelector('[data-filter-form]');
    const results = document.querySelector('[data-results]');
    if (!form || !results) return;

    const PD = window.PD || {};
    const panel = document.querySelector('[data-filters]');
    const totalEls = document.querySelectorAll('[data-results-total]');
    const countBadge = document.querySelector('[data-filters-count]');
    const desktop = window.matchMedia('(min-width: 1024px)');
    let controller = null;

    // ------------------------------------------------------------------
    // Адрес из текущего состояния формы (пустые значения убираем)
    // ------------------------------------------------------------------
    function urlFromForm() {
        const url = new URL(form.action, location.origin);
        const data = new FormData(form);
        for (const [key, value] of data.entries()) {
            if (String(value).trim() !== '') url.searchParams.append(key, value);
        }
        return url.pathname + url.search;
    }

    /** Состояние формы по адресу (для «назад/вперёд» и ссылок «убрать фильтр»). */
    function syncForm(href) {
        const params = new URL(href, location.origin).searchParams;
        [...form.elements].forEach((el) => {
            if (!el.name || el.type === 'hidden' || el.type === 'submit') return;
            if (el.type === 'checkbox') {
                el.checked = params.getAll(el.name).includes(el.value);
            } else {
                el.value = params.get(el.name) ?? '';
            }
        });
    }

    // ------------------------------------------------------------------
    // Загрузка выдачи
    // ------------------------------------------------------------------
    async function load(href, { push = true, scroll = false } = {}) {
        controller?.abort();
        controller = new AbortController();
        results.setAttribute('aria-busy', 'true');

        try {
            const data = await PD.api(href, { signal: controller.signal });
            results.innerHTML = data.html;
            totalEls.forEach((el) => { el.textContent = data.total; });
            if (countBadge) {
                countBadge.textContent = data.filtersCount;
                countBadge.hidden = data.filtersCount === 0;
            }
            document.title = data.title;
            if (push && data.url !== location.pathname + location.search) {
                history.pushState({ catalog: true }, '', data.url);
            }
            // Новые карточки сразу видимы (без анимации появления)
            results.querySelectorAll('.reveal').forEach((el) => el.classList.add('is-visible'));
            if (scroll) {
                const top = results.getBoundingClientRect().top + window.scrollY - 120;
                window.scrollTo({ top, behavior: PD.prefersReducedMotion?.() ? 'auto' : 'smooth' });
            }
        } catch (e) {
            if (e.name === 'AbortError') return;
            // Если что-то пошло не так — обычный переход по ссылке
            location.href = href;
        } finally {
            results.setAttribute('aria-busy', 'false');
        }
    }

    const apply = () => load(urlFromForm());
    const applyDebounced = PD.debounce ? PD.debounce(apply, 600) : apply;

    // Чекбоксы, переключатели и сортировка — сразу
    form.addEventListener('change', (e) => {
        if (e.target.type === 'number') return;
        apply();
    });
    document.querySelector('[data-sort]')?.addEventListener('change', apply);

    // Цена — после паузы в наборе
    form.querySelectorAll('input[type="number"]').forEach((input) => {
        input.addEventListener('input', applyDebounced);
        input.addEventListener('change', apply);
    });

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        apply();
        closePanel();
    });

    // Ссылки внутри выдачи: «убрать фильтр», «сбросить», страницы
    document.addEventListener('click', (e) => {
        const link = e.target.closest('[data-ajax-link], [data-filters-reset]');
        if (!link || e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;
        e.preventDefault();
        const isPage = link.closest('.pagination') !== null;
        syncForm(link.href);
        load(link.href, { scroll: isPage });
        if (link.matches('[data-filters-reset]')) closePanel();
    });

    window.addEventListener('popstate', () => {
        syncForm(location.href);
        load(location.href, { push: false });
    });
    history.replaceState({ catalog: true }, '');

    // ------------------------------------------------------------------
    // «Показать все ароматы»
    // ------------------------------------------------------------------
    document.querySelectorAll('[data-collapsible-toggle]').forEach((btn) => {
        const list = btn.previousElementSibling;
        btn.setAttribute('aria-expanded', 'false');
        const label = btn.innerHTML;
        btn.addEventListener('click', () => {
            const open = btn.getAttribute('aria-expanded') !== 'true';
            list.querySelectorAll('[data-extra]').forEach((li) => { li.hidden = !open; });
            btn.setAttribute('aria-expanded', String(open));
            btn.innerHTML = open ? label.replace(/Показать все \(\d+\)/, 'Свернуть') : label;
        });
    });

    // ------------------------------------------------------------------
    // Шторка фильтров на мобильных
    // ------------------------------------------------------------------
    function openPanel() {
        if (desktop.matches || !panel) return;
        panel.classList.add('is-open');
        PD.lockScroll?.(true);
        panel.querySelector('input, button')?.focus();
    }
    function closePanel() {
        if (!panel?.classList.contains('is-open')) return;
        panel.classList.remove('is-open');
        PD.lockScroll?.(false);
        document.querySelector('[data-filters-open]')?.focus();
    }
    document.querySelector('[data-filters-open]')?.addEventListener('click', openPanel);
    panel?.querySelectorAll('[data-filters-close]').forEach((el) => el.addEventListener('click', closePanel));
    document.addEventListener('keydown', (e) => e.key === 'Escape' && closePanel());
    desktop.addEventListener('change', (e) => e.matches && closePanel());
})();
