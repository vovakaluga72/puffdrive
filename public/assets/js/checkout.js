/* ==================================================================
   Оформление заказа: поля адреса под выбранный способ доставки,
   пересчёт стоимости доставки и итога, подстановка сохранённого адреса.
   Итоговую сумму всё равно пересчитывает сервер при оформлении.
   ================================================================== */
(() => {
    'use strict';

    const PD = window.PD || {};
    const form = document.querySelector('[data-checkout]');
    if (!form) return;

    const subtotal = Number(form.dataset.subtotal) || 0;      // сумма после промокода
    const threshold = Number(form.dataset.threshold) || 0;    // порог бесплатной доставки
    const costBox = form.querySelector('[data-delivery-cost]');
    const totalBox = form.querySelector('[data-total]');
    const wrappers = form.querySelectorAll('[data-field]');
    const savedBox = form.querySelector('[data-saved-addresses]');

    function deliveryCost(radio) {
        if (radio.dataset.free === '1') return 0;
        if (threshold > 0 && subtotal >= threshold) return 0;
        return Number(radio.dataset.cost) || 0;
    }

    function update() {
        const radio = form.querySelector('input[name="delivery_method"]:checked');
        if (!radio) return;

        const fields = radio.dataset.fields ? radio.dataset.fields.split(',') : [];
        wrappers.forEach((wrap) => {
            const name = wrap.dataset.field;
            wrap.hidden = name === 'pickup-note' ? radio.value !== 'pickup' : !fields.includes(name);
        });
        // Сохранённые адреса нужны только для доставки по адресу
        if (savedBox) savedBox.hidden = !fields.includes('address');

        // Курьер работает только по Гродно — город фиксирован
        const city = form.querySelector('[name="city"]');
        if (radio.value === 'courier' && city) city.value = 'Гродно';

        const cost = deliveryCost(radio);
        costBox.innerHTML = cost > 0 ? PD.priceTag(cost, 'price--inline') : 'Бесплатно';
        totalBox.innerHTML = PD.priceBlock(subtotal + cost, 'lg');
    }

    form.querySelectorAll('input[name="delivery_method"]').forEach((radio) => radio.addEventListener('change', update));

    form.querySelector('[data-saved-address]')?.addEventListener('change', (e) => {
        const option = e.target.selectedOptions[0];
        if (!option || !option.value) return;
        ['city', 'address', 'postcode'].forEach((name) => {
            const input = form.querySelector(`[name="${name}"]`);
            if (input) {
                input.value = option.dataset[name] || '';
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
    });

    update();
})();
