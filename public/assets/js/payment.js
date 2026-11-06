/* ==================================================================
   Тестовая страница оплаты: живой предпросмотр карты и индикатор
   загрузки. Данные карты не отправляются (у полей нет атрибута name).
   ================================================================== */
(() => {
    'use strict';

    const number = document.querySelector('[data-card-number]');
    const exp = document.querySelector('[data-card-exp]');
    const holder = document.querySelector('[data-card-holder]');
    const preview = {
        number: document.querySelector('[data-card-preview]'),
        exp: document.querySelector('[data-exp-preview]'),
        holder: document.querySelector('[data-holder-preview]'),
    };

    number?.addEventListener('input', () => {
        const digits = number.value.replace(/\D/g, '').slice(0, 16);
        number.value = digits.replace(/(\d{4})(?=\d)/g, '$1 ');
        preview.number.textContent = (digits.padEnd(16, '•')).replace(/(.{4})(?=.)/g, '$1 ');
    });
    exp?.addEventListener('input', () => {
        const digits = exp.value.replace(/\D/g, '').slice(0, 4);
        exp.value = digits.length > 2 ? `${digits.slice(0, 2)}/${digits.slice(2)}` : digits;
        preview.exp.textContent = exp.value || 'MM/YY';
    });
    holder?.addEventListener('input', () => {
        preview.holder.textContent = holder.value.toUpperCase() || 'CARDHOLDER';
    });

    // Блокируем повторное нажатие на время «обработки банком»
    document.querySelectorAll('[data-pay-form]').forEach((form) => {
        form.addEventListener('submit', () => {
            document.querySelectorAll('[data-pay-form] button').forEach((b) => { b.disabled = true; });
            form.querySelector('button').classList.add('is-loading');
        });
    });
})();
