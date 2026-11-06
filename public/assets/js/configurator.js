/* ==================================================================
   Конфигуратор аппарата «Сухой туман».

   Покупатель нажимает на слот панели и выбирает для него аромат.
   Правила: число слотов берётся из data-slots (Classic — 4, остальные — 6), один аромат нельзя
   выбрать дважды, «В корзину» доступна только когда заполнены все слоты.
   Выбор сохраняется в localStorage — при возврате на страницу не пропадёт.
   Сервер проверяет конфигурацию ещё раз (MachineConfig::validate).
   ================================================================== */
(() => {
    'use strict';

    const root = document.querySelector('[data-configurator]');
    const form = document.getElementById('buy-form');
    if (!root || !form) return;

    const SLOTS = Number(root.dataset.slots) || 6;
    const aromas = JSON.parse(root.dataset.aromas || '[]');
    const byId = new Map(aromas.map((a) => [a.id, a]));
    const storageKey = `pd-config-${root.dataset.product}`;

    const pills = [...root.querySelectorAll('[data-slot]')];
    const options = [...root.querySelectorAll('[data-aroma]')];
    const pickerTitle = root.querySelector('[data-picker-title]');
    const clearBtn = root.querySelector('[data-picker-clear]');
    const picker = root.querySelector('[data-picker]');
    const summary = root.querySelector('[data-config-summary]');
    const filledEl = root.querySelector('[data-config-filled]');
    const bar = root.querySelector('[data-config-bar]');
    const submitLabel = root.querySelector('[data-config-submit-label]');
    const inputsBox = form.querySelector('[data-slot-inputs]');
    const hint = form.querySelector('[data-config-hint]');
    const ledTracks = root.querySelectorAll('.machine__led-track span');
    const defaultMarquee = ledTracks[0]?.textContent || '';

    let slots = new Array(SLOTS).fill(null);
    let active = 0;

    // ------------------------------------------------------------------
    // Сохранение выбора между визитами (браузерное хранилище может быть недоступно)
    // ------------------------------------------------------------------
    function restore() {
        try {
            const saved = JSON.parse(localStorage.getItem(storageKey) || 'null');
            if (Array.isArray(saved) && saved.length === SLOTS) {
                const seen = new Set();
                slots = saved.map((id) => (byId.has(id) && !seen.has(id) && seen.add(id) ? id : null));
            }
        } catch { /* нет доступа к localStorage — начинаем с пустой панели */ }
        const firstEmpty = slots.indexOf(null);
        active = firstEmpty === -1 ? 0 : firstEmpty;
    }
    function save() {
        try { localStorage.setItem(storageKey, JSON.stringify(slots)); } catch { /* ignore */ }
    }

    // ------------------------------------------------------------------
    // Отрисовка всего состояния
    // ------------------------------------------------------------------
    function render() {
        const filled = slots.filter(Boolean).length;
        const complete = filled === SLOTS;

        pills.forEach((pill, i) => {
            const aroma = byId.get(slots[i]);
            pill.classList.toggle('is-filled', Boolean(aroma));
            pill.classList.toggle('is-active', i === active);
            pill.style.setProperty('--aroma', aroma ? aroma.color : '#3a3a42');
            pill.querySelector('[data-slot-label]').textContent = aroma ? aroma.name : `Слот ${i + 1}`;
            pill.setAttribute('aria-label', `Слот ${i + 1}: ${aroma ? aroma.name : 'не выбран'}`);
            pill.setAttribute('aria-pressed', String(i === active));
        });

        const current = byId.get(slots[active]);
        pickerTitle.textContent = current
            ? `Слот ${active + 1}: ${current.name} — можно заменить`
            : `Слот ${active + 1}: выберите аромат`;
        clearBtn.hidden = !current;

        options.forEach((btn) => {
            const id = Number(btn.dataset.aroma);
            const usedAt = slots.indexOf(id);
            const isHere = usedAt === active;
            btn.setAttribute('aria-selected', String(isHere));
            btn.disabled = usedAt !== -1 && !isHere;
            btn.querySelector('[data-aroma-state]').textContent = usedAt !== -1 ? (isHere ? '✓ здесь' : `слот ${usedAt + 1}`) : '';
        });

        filledEl.textContent = String(filled);
        bar.style.width = `${(filled / SLOTS) * 100}%`;

        summary.innerHTML = slots.map((id, i) => {
            const aroma = byId.get(id);
            const name = aroma ? escapeHtml(aroma.name) : '<span class="is-empty">не выбран</span>';
            const dot = aroma ? `<span class="aroma-dot" style="--dot:${aroma.color}"></span>` : '';
            return `<li><span class="config-summary__slot">Слот ${i + 1} —</span> ${dot}${name}</li>`;
        }).join('');

        // Скрытые поля формы: slots[] по порядку слотов
        inputsBox.innerHTML = complete
            ? slots.map((id) => `<input type="hidden" name="slots[]" value="${id}">`).join('')
            : '';

        submitLabel.textContent = complete ? 'В корзину' : `Заполните ещё ${SLOTS - filled} ${plural(SLOTS - filled, ['слот', 'слота', 'слотов'])}`;
        if (hint) {
            hint.classList.toggle('is-done', complete);
            hint.querySelector('span').innerHTML = complete
                ? 'Конфигурация готова: ' + slots.map((id, i) => `${i + 1} — ${escapeHtml(byId.get(id).name)}`).join(', ')
                : `Чтобы добавить аппарат в корзину, выберите ароматы для всех слотов (${SLOTS}) в <a href="#configurator">конфигураторе ниже</a>.`;
        }

        // Бегущая строка табло показывает выбранный аромат
        const text = current ? `АРОМАТ ${current.name.toUpperCase()} · СУХОЙ ТУМАН · PUFF DRIVE · ` : defaultMarquee;
        ledTracks.forEach((span) => { span.textContent = text; });

        form.dataset.configReady = complete ? '1' : '0';
        form.dispatchEvent(new CustomEvent('config:change'));
    }

    // ------------------------------------------------------------------
    // Действия
    // ------------------------------------------------------------------
    function setActive(index, scrollToPicker = false) {
        active = index;
        render();
        if (scrollToPicker && window.matchMedia('(max-width: 1099px)').matches) {
            picker.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function assign(aromaId) {
        slots[active] = aromaId;
        pulse(active);
        // Следующий пустой слот (сначала после текущего, затем с начала)
        const after = slots.findIndex((id, i) => i > active && id === null);
        const any = slots.indexOf(null);
        if (after !== -1) active = after;
        else if (any !== -1) active = any;
        save();
        render();
    }

    function pulse(index) {
        const pill = pills[index];
        pill.classList.remove('is-pulse');
        void pill.offsetWidth;
        pill.classList.add('is-pulse');
    }

    function fillPopular() {
        const pool = [...aromas.filter((a) => a.popular), ...aromas.filter((a) => !a.popular)]
            .map((a) => a.id)
            .filter((id) => !slots.includes(id));
        slots = slots.map((id) => id ?? pool.shift() ?? null);
        slots.forEach((_, i) => pulse(i));
        active = Math.max(0, slots.indexOf(null));
        save();
        render();
    }

    function reset() {
        slots = new Array(SLOTS).fill(null);
        active = 0;
        save();
        render();
    }

    pills.forEach((pill, i) => pill.addEventListener('click', () => setActive(i, true)));
    options.forEach((btn) => btn.addEventListener('click', () => !btn.disabled && assign(Number(btn.dataset.aroma))));
    clearBtn.addEventListener('click', () => { slots[active] = null; save(); render(); });
    root.querySelector('[data-config-popular]').addEventListener('click', fillPopular);
    root.querySelector('[data-config-reset]').addEventListener('click', reset);

    // ------------------------------------------------------------------
    // Утилиты
    // ------------------------------------------------------------------
    function escapeHtml(s) {
        return window.PD?.escape ? window.PD.escape(s) : String(s).replace(/[&<>"']/g, '');
    }
    function plural(n, forms) {
        const a = Math.abs(n) % 100;
        const b = a % 10;
        if (a > 10 && a < 20) return forms[2];
        if (b > 1 && b < 5) return forms[1];
        if (b === 1) return forms[0];
        return forms[2];
    }

    restore();
    render();
})();
