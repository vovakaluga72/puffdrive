/* ==================================================================
   Корзина: изменение количества, удаление и промокод без перезагрузки.
   Сервер возвращает уже готовый HTML корзины (cart/_body.php),
   поэтому итоги всегда считаются на сервере и не расходятся с заказом.
   Без JS формы работают обычной отправкой.
   ================================================================== */
(() => {
    'use strict';

    const PD = window.PD || {};
    const cart = document.querySelector('[data-cart]');
    if (!cart) return;

    let busy = false;

    async function send(form, submitter = null) {
        if (busy) return;
        busy = true;
        cart.classList.add('is-busy');

        const data = new FormData(form);
        if (submitter?.name) data.append(submitter.name, submitter.value);

        try {
            const res = await PD.api(form.action, { method: 'POST', data });
            const focusKey = document.activeElement?.closest('[data-line]')?.dataset.line;
            cart.innerHTML = res.html;
            PD.setCartCount?.(res.count);
            if (res.message) PD.toast?.(res.message, res.type === 'error' ? 'error' : (res.type || 'info'), 3500);
            // Возвращаем фокус в ту же строку, чтобы клавиатурой было удобно
            if (focusKey) cart.querySelector(`[data-line="${CSS.escape(focusKey)}"] .qty__input`)?.focus();
        } catch (err) {
            PD.toast?.(err.message, 'error');
        } finally {
            cart.classList.remove('is-busy');
            busy = false;
        }
    }

    // Все формы внутри корзины — через AJAX (делегирование: HTML перерисовывается)
    cart.addEventListener('submit', (e) => {
        const form = e.target.closest('[data-cart-qty], [data-cart-remove], [data-cart-action]');
        if (!form) return;
        e.preventDefault();
        send(form, e.submitter && e.submitter.name ? e.submitter : null);
    });

    // Ручной ввод количества — отправка после паузы
    let timer;
    cart.addEventListener('input', (e) => {
        const input = e.target.closest('.qty__input');
        if (!input) return;
        clearTimeout(timer);
        timer = setTimeout(() => {
            const value = parseInt(input.value, 10);
            if (!Number.isNaN(value) && value >= 1) send(input.form);
        }, 600);
    });
})();
