/* ==================================================================
   Puff Drive — общий JavaScript сайта.
   - PD.api(): fetch-обёртка с CSRF-токеном и разбором JSON-ошибок
   - PD.toast(): всплывающие уведомления
   - шапка: тень при прокрутке, мобильное меню, живой поиск
   - анимация появления блоков при прокрутке
   ================================================================== */
(() => {
    'use strict';

    const PD = (window.PD = window.PD || {});
    const root = document.documentElement;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // ------------------------------------------------------------------
    // Вспомогательные функции
    // ------------------------------------------------------------------

    /** Экранирование текста перед вставкой в HTML (защита от XSS на клиенте). */
    PD.escape = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[ch]);

    PD.debounce = (fn, wait = 250) => {
        let timer;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => fn(...args), wait);
        };
    };

    PD.prefersReducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /**
     * Цена в формате сайта «2 299.00 Б» — та же разметка, что у PHP-хелпера price_tag().
     * Знак рубля должен совпадать с byn_sign() в app/helpers.php.
     */
    const BYN_SIGN = '<svg class="price__cur" viewBox="0 0 14 20" aria-hidden="true" focusable="false">'
        + '<path d="M11.8 2.6H4.2v14.8h4.4a3.8 3.8 0 0 0 0-7.6H4.2" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linejoin="miter"/>'
        + '<path d="M0.4 9.8h4.8" fill="none" stroke="currentColor" stroke-width="2"/></svg>';

    PD.priceTag = (amount, modifier = '') => {
        const [int, dec] = Number(amount).toFixed(2).split('.');
        const grouped = int.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        return `<span class="price${modifier ? ' ' + modifier : ''}" aria-label="${int},${dec} белорусских рублей">`
            + `<span class="price__int" aria-hidden="true">${grouped}</span>`
            + `<span class="price__dec" aria-hidden="true">.${dec}</span>${BYN_SIGN}</span>`;
    };

    PD.priceBlock = (amount, size = 'md') =>
        `<div class="price-block price-block--${size}"><div class="price-block__main">${PD.priceTag(amount)}</div></div>`;

    /**
     * Запрос к серверу. Всегда передаёт CSRF-токен и признак AJAX.
     * При ошибке бросает Error с полями status и data (ответ сервера).
     */
    PD.api = async (url, { method = 'GET', data = null, signal } = {}) => {
        const options = {
            method,
            credentials: 'same-origin',
            signal,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken,
                Accept: 'application/json',
            },
        };
        if (data instanceof FormData) {
            options.body = data;
        } else if (data !== null) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(data);
        }

        const response = await fetch(url, options);
        let json = null;
        try {
            json = await response.json();
        } catch {
            /* ответ не JSON */
        }

        if (!response.ok || (json && json.ok === false)) {
            const error = new Error(json?.error || 'Не удалось выполнить запрос. Попробуйте ещё раз.');
            error.status = response.status;
            error.data = json;
            throw error;
        }
        return json;
    };

    // Блокировка прокрутки страницы (меню, модальные окна) с учётом вложенности
    let lockCount = 0;
    PD.lockScroll = (lock) => {
        lockCount = Math.max(0, lockCount + (lock ? 1 : -1));
        root.classList.toggle('is-locked', lockCount > 0);
    };

    // ------------------------------------------------------------------
    // Уведомления
    // ------------------------------------------------------------------
    const toastIcons = {
        success: '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        error: '<path d="M12 4 2.8 19.5h18.4z"/><path d="M12 10v4M12 17v.5"/>',
        info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7.5v.5"/>',
    };
    const svg = (paths) => `<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths}</svg>`;

    PD.toast = (text, type = 'info', timeout = 4500) => {
        const container = document.querySelector('[data-toasts]');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `toast toast--${type}`;
        toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
        toast.innerHTML = `${svg(toastIcons[type] || toastIcons.info)}
            <div class="toast__text">${PD.escape(text)}</div>
            <button class="toast__close" type="button" aria-label="Закрыть">${svg('<path d="M6 6l12 12M18 6 6 18"/>')}</button>`;

        const remove = () => {
            toast.classList.add('is-leaving');
            toast.addEventListener('animationend', () => toast.remove(), { once: true });
        };
        toast.querySelector('.toast__close').addEventListener('click', remove);
        container.appendChild(toast);
        if (timeout > 0) setTimeout(remove, timeout);
    };

    PD.setCartCount = (count) => {
        document.querySelectorAll('[data-cart-count]').forEach((badge) => {
            badge.textContent = String(count);
            badge.classList.toggle('is-empty', count <= 0);
            badge.classList.remove('is-bump');
            void badge.offsetWidth; // перезапуск анимации
            badge.classList.add('is-bump');
        });
    };

    function initToasts() {
        const container = document.querySelector('[data-toasts]');
        if (!container?.dataset.initial) return;
        try {
            JSON.parse(container.dataset.initial).forEach((m) => PD.toast(m.text, m.type));
        } catch {
            /* игнорируем битые данные */
        }
    }

    // ------------------------------------------------------------------
    // Шапка: состояние при прокрутке
    // ------------------------------------------------------------------
    function initHeader() {
        const header = document.querySelector('[data-header]');
        if (!header) return;
        let ticking = false;
        const update = () => {
            header.classList.toggle('is-scrolled', window.scrollY > 24);
            ticking = false;
        };
        window.addEventListener('scroll', () => {
            if (!ticking) {
                ticking = true;
                requestAnimationFrame(update);
            }
        }, { passive: true });
        update();
    }

    // ------------------------------------------------------------------
    // Мобильное меню
    // ------------------------------------------------------------------
    function initMobileNav() {
        const nav = document.querySelector('[data-nav]');
        const openBtn = document.querySelector('[data-nav-open]');
        if (!nav || !openBtn) return;

        const open = () => {
            nav.classList.add('is-open');
            nav.setAttribute('aria-hidden', 'false');
            openBtn.setAttribute('aria-expanded', 'true');
            PD.lockScroll(true);
            setTimeout(() => nav.querySelector('.mobile-nav__panel a, .mobile-nav__panel input')?.focus(), 50);
        };
        const close = () => {
            if (!nav.classList.contains('is-open')) return;
            nav.classList.remove('is-open');
            nav.setAttribute('aria-hidden', 'true');
            openBtn.setAttribute('aria-expanded', 'false');
            PD.lockScroll(false);
            openBtn.focus();
        };

        openBtn.addEventListener('click', open);
        nav.querySelectorAll('[data-nav-close]').forEach((el) => el.addEventListener('click', close));
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') close();
        });
        // На широком экране меню не нужно
        window.matchMedia('(min-width: 1100px)').addEventListener('change', (e) => e.matches && close());
    }

    // ------------------------------------------------------------------
    // Поиск с живыми подсказками
    // ------------------------------------------------------------------
    function initSearch() {
        const form = document.querySelector('[data-search]');
        if (!form) return;
        const input = form.querySelector('[data-search-input]');
        const box = form.querySelector('[data-search-suggest]');
        const toggle = document.querySelector('[data-search-toggle]');
        const suggestUrl = form.dataset.suggestUrl;
        let controller = null;
        let activeIndex = -1;

        const hide = () => {
            box.hidden = true;
            box.innerHTML = '';
            activeIndex = -1;
            input.setAttribute('aria-expanded', 'false');
        };

        const render = (items, query) => {
            if (!items.length) {
                box.innerHTML = `<div class="search-suggest__empty">Ничего не найдено по запросу «${PD.escape(query)}»</div>`;
            } else {
                box.innerHTML = items.map((item, i) => `
                    <a class="search-suggest__item" role="option" id="suggest-${i}" href="${PD.escape(item.url)}">
                        <span class="aroma-dot" style="--dot:${PD.escape(item.color || '#c9a96e')}"></span>
                        <span class="search-suggest__name">${PD.escape(item.name)}</span>
                        <span class="search-suggest__price">${item.price_html || ''}</span>
                    </a>`).join('')
                    + `<a class="search-suggest__all" href="${PD.escape(form.action)}?q=${encodeURIComponent(query)}">Все результаты</a>`;
            }
            box.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        };

        const fetchSuggest = PD.debounce(async () => {
            const query = input.value.trim();
            if (query.length < 2 || !suggestUrl) {
                hide();
                return;
            }
            controller?.abort();
            controller = new AbortController();
            try {
                const data = await PD.api(`${suggestUrl}?q=${encodeURIComponent(query)}`, { signal: controller.signal });
                render(data.items || [], query);
            } catch (e) {
                if (e.name !== 'AbortError') hide();
            }
        }, 250);

        input.addEventListener('input', fetchSuggest);
        input.addEventListener('keydown', (e) => {
            const items = [...box.querySelectorAll('.search-suggest__item')];
            if (e.key === 'Escape') {
                hide();
                if (form.classList.contains('is-open')) form.classList.remove('is-open');
                return;
            }
            if (!items.length || !['ArrowDown', 'ArrowUp', 'Enter'].includes(e.key)) return;
            if (e.key === 'Enter') {
                if (activeIndex >= 0) {
                    e.preventDefault();
                    items[activeIndex].click();
                }
                return;
            }
            e.preventDefault();
            activeIndex = (activeIndex + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
            items.forEach((el, i) => el.classList.toggle('is-active', i === activeIndex));
            input.setAttribute('aria-activedescendant', items[activeIndex].id);
        });

        document.addEventListener('click', (e) => {
            if (!form.contains(e.target) && e.target !== toggle && !toggle?.contains(e.target)) {
                hide();
                form.classList.remove('is-open');
            }
        });

        toggle?.addEventListener('click', () => {
            form.classList.toggle('is-open');
            if (form.classList.contains('is-open')) input.focus();
        });

        form.addEventListener('submit', (e) => {
            if (input.value.trim() === '') {
                e.preventDefault();
                input.focus();
            }
        });
    }

    // ------------------------------------------------------------------
    // Появление блоков при прокрутке
    // ------------------------------------------------------------------
    function initReveal() {
        const items = document.querySelectorAll('.reveal');
        if (!items.length) return;

        if (!('IntersectionObserver' in window) || PD.prefersReducedMotion()) {
            items.forEach((el) => el.classList.add('is-visible'));
            return;
        }

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

        items.forEach((el) => observer.observe(el));
        PD.observeReveal = (el) => observer.observe(el); // для блоков, добавленных через fetch
    }

    // Скрипты подключены с defer — DOM уже готов
    initToasts();
    initHeader();
    initMobileNav();
    initSearch();
    initReveal();
})();
