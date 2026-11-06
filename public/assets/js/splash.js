/* ==================================================================
   Приветственный экран.
   - закрывается кнопками «Войти в магазин», «Пропустить» и клавишей Esc;
   - «Хочу свой аппарат» закрывает экран и ведёт на страницу заявки;
   - после закрытия ставится cookie на 30 дней — экран больше не показывается;
   - фокус клавиатуры удерживается внутри экрана, пока он открыт.
   ================================================================== */
(() => {
    'use strict';

    const splash = document.querySelector('[data-splash]');
    if (!splash) return;

    const COOKIE_NAME = 'pd_splash_seen';
    const COOKIE_DAYS = 30;
    const root = document.documentElement;
    let closing = false;

    function remember() {
        const secure = location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = `${COOKIE_NAME}=1; max-age=${COOKIE_DAYS * 86400}; path=/; SameSite=Lax${secure}`;
    }

    /** Убираем ?splash=1 из адреса, чтобы экран не появился при обновлении. */
    function cleanUrl() {
        const url = new URL(location.href);
        if (url.searchParams.has('splash')) {
            url.searchParams.delete('splash');
            history.replaceState(null, '', url.pathname + url.search + url.hash);
        }
    }

    function close(redirectTo = null) {
        if (closing) return;
        closing = true;

        remember();
        cleanUrl();

        splash.classList.add('is-leaving');
        root.classList.remove('has-splash');

        const finish = () => {
            const canvas = splash.querySelector('canvas[data-fog]');
            if (canvas && window.PD?.fogs?.has(canvas)) {
                window.PD.fogs.get(canvas).destroy();
                window.PD.fogs.delete(canvas);
            }
            splash.remove();
            document.getElementById('main')?.focus({ preventScroll: true });
        };

        if (redirectTo) {
            setTimeout(() => { location.href = redirectTo; }, 450);
            return;
        }
        splash.addEventListener('transitionend', (e) => e.target === splash && finish(), { once: true });
        setTimeout(() => splash.isConnected && finish(), 1100); // запасной вариант
    }

    splash.querySelectorAll('[data-splash-close]').forEach((el) => {
        el.addEventListener('click', (e) => {
            e.preventDefault();
            close();
        });
    });

    splash.querySelectorAll('[data-splash-go]').forEach((el) => {
        el.addEventListener('click', (e) => {
            e.preventDefault();
            close(el.href);
        });
    });

    document.addEventListener('keydown', (e) => {
        if (closing) return;
        if (e.key === 'Escape') {
            close();
            return;
        }
        // Удерживаем фокус внутри экрана
        if (e.key === 'Tab') {
            const focusable = [...splash.querySelectorAll('a[href], button:not([disabled])')];
            if (!focusable.length) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            } else if (!splash.contains(document.activeElement)) {
                e.preventDefault();
                first.focus();
            }
        }
    });

    // Фокус на главную кнопку после появления элементов
    setTimeout(() => !closing && splash.querySelector('.btn--primary')?.focus({ preventScroll: true }), 900);
})();
