/* ==================================================================
   Страница товара: выбор варианта (цена меняется), количество,
   галерея, добавление в корзину без перезагрузки.
   ================================================================== */
(() => {
    'use strict';

    const PD = window.PD || {};
    const form = document.querySelector('[data-buy-form]');
    if (!form) return;

    const variants = JSON.parse(form.dataset.variants || '[]');
    const priceBox = document.querySelector('[data-price]');
    const stockLine = form.querySelector('[data-stock]');
    const qtyInput = form.querySelector('input[name="quantity"]');
    const submitBtn = form.querySelector('[data-buy-submit]');
    const submitLabel = form.querySelector('[data-buy-label]');
    const discountBadge = document.querySelector('[data-discount-badge]');
    const saleEnds = document.querySelector('[data-sale-ends]');
    const isMachine = document.querySelector('[data-configurator]') !== null;

    let current = variants.find((v) => String(v.id) === String(form.querySelector('[name="variant_id"]:checked, [name="variant_id"]')?.value)) || variants[0];

    // ------------------------------------------------------------------
    // Доступность кнопки «В корзину»: наличие + (для аппарата) готовая конфигурация
    // ------------------------------------------------------------------
    function updateButtons() {
        const inStock = current && current.stock > 0;
        const configReady = !isMachine || form.dataset.configReady === '1';
        const enabled = inStock && configReady;

        submitBtn.disabled = !enabled;
        submitLabel.textContent = !inStock ? 'Нет в наличии' : (configReady ? 'В корзину' : 'Настройте ароматы');

        document.querySelectorAll('[data-config-submit]').forEach((btn) => {
            btn.disabled = !enabled;
        });
    }

    function renderStock(variant) {
        if (!stockLine) return;
        if (variant.stock <= 0) {
            stockLine.innerHTML = '<span class="stock-line__dot"></span> Нет в наличии';
        } else if (variant.stock <= 5) {
            stockLine.innerHTML = `<span class="stock-line__dot is-low"></span> Осталось мало: ${variant.stock} шт.`;
        } else {
            stockLine.innerHTML = '<span class="stock-line__dot is-in"></span> В наличии';
        }
    }

    function selectVariant(variant) {
        current = variant;
        priceBox.innerHTML = variant.priceHtml; // HTML сформирован сервером хелпером price()
        document.querySelectorAll('[data-sku]').forEach((el) => { el.textContent = variant.sku; });
        renderStock(variant);

        if (discountBadge) {
            discountBadge.hidden = !variant.discount;
            discountBadge.textContent = `−${variant.discount}%`;
        }
        if (saleEnds) {
            saleEnds.hidden = !variant.saleEnds;
            saleEnds.querySelector('span').textContent = variant.saleEnds;
        }

        const max = Math.max(1, Math.min(99, variant.stock));
        qtyInput.max = String(max);
        if (Number(qtyInput.value) > max) qtyInput.value = String(max);
        updateQtyButtons();
        updateButtons();

        // Выбранный вариант — в адресе, чтобы ссылкой можно было поделиться
        const url = new URL(location.href);
        url.searchParams.set('variant', variant.id);
        history.replaceState(history.state, '', url.pathname + url.search + url.hash);
    }

    form.querySelectorAll('input[name="variant_id"][type="radio"]').forEach((radio) => {
        radio.addEventListener('change', () => {
            const variant = variants.find((v) => String(v.id) === radio.value);
            if (variant) selectVariant(variant);
        });
    });

    // ------------------------------------------------------------------
    // Количество
    // ------------------------------------------------------------------
    const minus = form.querySelector('[data-qty-minus]');
    const plus = form.querySelector('[data-qty-plus]');

    function clampQty() {
        const max = Number(qtyInput.max) || 99;
        let value = parseInt(qtyInput.value, 10);
        if (Number.isNaN(value) || value < 1) value = 1;
        if (value > max) value = max;
        qtyInput.value = String(value);
        updateQtyButtons();
    }
    function updateQtyButtons() {
        const value = Number(qtyInput.value);
        minus.disabled = value <= 1;
        plus.disabled = value >= (Number(qtyInput.max) || 99);
    }
    minus.addEventListener('click', () => { qtyInput.value = String(Number(qtyInput.value) - 1); clampQty(); });
    plus.addEventListener('click', () => { qtyInput.value = String(Number(qtyInput.value) + 1); clampQty(); });
    qtyInput.addEventListener('change', clampQty);
    updateQtyButtons();

    // ------------------------------------------------------------------
    // Добавление в корзину
    // ------------------------------------------------------------------
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (submitBtn.disabled) return;

        const buttons = [submitBtn, ...document.querySelectorAll('[data-config-submit]')];
        buttons.forEach((b) => b.classList.add('is-loading'));

        try {
            const data = await PD.api(form.action, { method: 'POST', data: new FormData(form) });
            PD.setCartCount?.(data.count);
            const item = data.item;
            const text = `Добавлено в корзину: ${item.name}${item.variant ? ', ' + item.variant : ''} × ${data.added}`;
            PD.toast?.(text, 'success', 5000);
        } catch (err) {
            PD.toast?.(err.message, 'error', 6000);
        } finally {
            buttons.forEach((b) => b.classList.remove('is-loading'));
        }
    });

    // Конфигуратор сообщает о готовности конфигурации
    form.addEventListener('config:change', updateButtons);

    // ------------------------------------------------------------------
    // Галерея
    // ------------------------------------------------------------------
    const mainImg = document.querySelector('[data-gallery-main]');
    document.querySelectorAll('[data-gallery-thumb]').forEach((thumb) => {
        thumb.addEventListener('click', () => {
            if (!mainImg) return;
            mainImg.style.opacity = '0';
            setTimeout(() => {
                mainImg.src = thumb.dataset.galleryThumb;
                mainImg.alt = thumb.dataset.alt || '';
                mainImg.style.opacity = '1';
            }, 150);
            document.querySelectorAll('[data-gallery-thumb]').forEach((t) => t.classList.toggle('is-active', t === thumb));
        });
    });

    updateButtons();
})();
