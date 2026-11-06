/* ==================================================================
   Формы: клиентские подсказки (валидация повторяется на сервере!),
   маска белорусского телефона, показ пароля, индикатор надёжности,
   подтверждение опасных действий, защита от двойной отправки.
   ================================================================== */
(() => {
    'use strict';

    // ------------------------------------------------------------------
    // Телефон: +375 (XX) XXX-XX-XX
    // ------------------------------------------------------------------
    const PHONE_RE = /^375(25|29|33|44|1[5-7]|2[1-3])\d{7}$/;

    /** Приводит любые цифры к формату 375XXXXXXXXX (принимает 80XX…, 375…, XX…). */
    function phoneDigits(value) {
        let d = String(value).replace(/\D/g, '');
        if (d.startsWith('80')) d = '375' + d.slice(2);
        else if (!d.startsWith('375') && d.length <= 9 && d.length > 0) d = '375' + d;
        return d.slice(0, 12);
    }

    function formatPhone(digits) {
        if (!digits) return '';
        const local = digits.slice(3);
        let out = '+375';
        if (local.length > 0) out += ' (' + local.slice(0, 2);
        if (local.length >= 2) out += ')';
        if (local.length > 2) out += ' ' + local.slice(2, 5);
        if (local.length > 5) out += '-' + local.slice(5, 7);
        if (local.length > 7) out += '-' + local.slice(7, 9);
        return out;
    }

    function initPhone(input) {
        const apply = () => {
            const digits = phoneDigits(input.value);
            input.value = formatPhone(digits);
        };
        input.addEventListener('input', (e) => {
            // Стирание: не мешаем удалять скобки и дефисы
            if (e.inputType && e.inputType.startsWith('delete')) return;
            apply();
        });
        input.addEventListener('blur', apply);
        input.addEventListener('focus', () => {
            if (input.value === '') input.value = '+375 (';
        });
        input.addEventListener('blur', () => {
            if (phoneDigits(input.value) === '375') input.value = '';
        });
        if (input.value) apply();
    }

    // ------------------------------------------------------------------
    // Пароль: показать/скрыть и индикатор надёжности
    // ------------------------------------------------------------------
    function initPasswordToggle(button) {
        const input = button.parentElement.querySelector('input');
        button.addEventListener('click', () => {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(show));
            button.setAttribute('aria-label', show ? 'Скрыть пароль' : 'Показать пароль');
            input.focus();
        });
    }

    function passwordScore(value) {
        if (!value) return 0;
        let score = 0;
        if (value.length >= 8) score++;
        if (value.length >= 12) score++;
        if (/\d/.test(value) && /\p{L}/u.test(value)) score++;
        if (/[^\p{L}\d]/u.test(value) || (/\p{Lu}/u.test(value) && /\p{Ll}/u.test(value))) score++;
        return Math.max(1, Math.min(4, score));
    }

    function initStrength(input) {
        const labels = ['', 'Слабый', 'Средний', 'Хороший', 'Надёжный'];
        const meter = document.createElement('div');
        meter.className = 'strength';
        meter.setAttribute('aria-live', 'polite');
        meter.innerHTML = '<div class="strength__bars"><span></span><span></span><span></span><span></span></div><span class="strength__label"></span>';
        const field = input.closest('.field');
        field.insertBefore(meter, field.querySelector('.field__hint, .field__error'));
        meter.hidden = true;

        input.addEventListener('input', () => {
            const level = passwordScore(input.value);
            meter.hidden = level === 0;
            meter.dataset.level = String(level);
            meter.querySelector('.strength__label').textContent = labels[level] ? `Пароль: ${labels[level].toLowerCase()}` : '';
        });
    }

    // ------------------------------------------------------------------
    // Валидация на клиенте (подсказки до отправки на сервер)
    // ------------------------------------------------------------------
    function fieldLabel(input) {
        const label = input.id ? document.querySelector(`label[for="${input.id}"]`) : null;
        return label ? label.textContent.replace('*', '').trim() : 'Поле';
    }

    function validateField(input) {
        const value = input.type === 'checkbox' ? input.checked : input.value.trim();
        const label = fieldLabel(input);

        if (input.required && (value === '' || value === false)) {
            if (input.type === 'checkbox') {
                return input.name === 'consent'
                    ? 'Необходимо согласие на обработку персональных данных'
                    : 'Отметьте это поле';
            }
            return `Заполните поле «${label}»`;
        }
        if (value === '' || value === false) return '';

        if (input.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value)) {
            return 'Введите корректный email';
        }
        if ('phone' in input.dataset && !PHONE_RE.test(phoneDigits(value))) {
            return 'Введите номер в формате +375 (XX) XXX-XX-XX';
        }
        if (input.minLength > 0 && value.length < input.minLength) {
            return `«${label}»: минимум ${input.minLength} символов`;
        }
        if (input.maxLength > 0 && value.length > input.maxLength) {
            return `«${label}»: максимум ${input.maxLength} символов`;
        }
        if (input.pattern && !new RegExp(`^(?:${input.pattern})$`).test(value)) {
            return input.name === 'postcode' ? 'Индекс в Беларуси — 6 цифр' : `Неверный формат поля «${label}»`;
        }
        if ('passwordStrength' in input.dataset && !(/\p{L}/u.test(value) && /\d/.test(value))) {
            return 'Пароль должен содержать хотя бы одну букву и одну цифру';
        }
        if (input.dataset.match) {
            const other = input.form.querySelector(`[name="${input.dataset.match}"]`);
            if (other && other.value !== input.value) return 'Пароли не совпадают';
        }
        return '';
    }

    function showError(input, message) {
        const field = input.closest('.field');
        const box = field?.querySelector('.field__error');
        input.classList.toggle('is-invalid', message !== '');
        input.setAttribute('aria-invalid', message ? 'true' : 'false');
        if (box) {
            box.textContent = message;
            box.hidden = message === '';
        }
    }

    function initForm(form) {
        const inputs = () => [...form.querySelectorAll('input, textarea, select')]
            .filter((el) => !['hidden', 'submit', 'button'].includes(el.type) && el.name && !el.closest('[hidden]') && !el.closest('.hp-field'));

        inputs().forEach((input) => {
            const event = input.type === 'checkbox' ? 'change' : 'input';
            input.addEventListener(event, () => {
                if (input.classList.contains('is-invalid') || input.type === 'checkbox') {
                    showError(input, validateField(input));
                }
            });
            input.addEventListener('blur', () => {
                if (input.value.trim() !== '') showError(input, validateField(input));
            });
        });

        form.addEventListener('submit', (e) => {
            let firstInvalid = null;
            inputs().forEach((input) => {
                const message = validateField(input);
                showError(input, message);
                if (message && !firstInvalid) firstInvalid = input;
            });

            if (firstInvalid) {
                e.preventDefault();
                firstInvalid.focus();
                firstInvalid.closest('.field')?.scrollIntoView({ block: 'center', behavior: 'smooth' });
                return;
            }

            // Защита от двойного клика
            const submit = form.querySelector('[type="submit"]');
            if (submit) {
                submit.classList.add('is-loading');
                setTimeout(() => submit.classList.remove('is-loading'), 8000);
            }
        });
    }

    // ------------------------------------------------------------------
    // Мелкие поведения
    // ------------------------------------------------------------------

    // Подтверждение опасных действий (удаление)
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (e) => {
            if (!window.confirm(form.dataset.confirm)) e.preventDefault();
        });
    });

    // Профиль: поле «текущий пароль» появляется, только если email изменён
    document.querySelectorAll('[data-initial]').forEach((input) => {
        const box = input.form?.querySelector('[data-email-confirm]');
        if (!box) return;
        input.addEventListener('input', () => {
            box.hidden = input.value.trim().toLowerCase() === input.dataset.initial.toLowerCase()
                && !box.querySelector('.is-invalid');
        });
    });

    // Кнопка «Добавить адрес» раскрывает скрытую форму
    document.querySelectorAll('[data-reveal-form]').forEach((link) => {
        link.addEventListener('click', (e) => {
            const target = document.getElementById(link.dataset.revealForm);
            if (!target) return;
            e.preventDefault();
            target.hidden = false;
            link.hidden = true;
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            target.querySelector('input:not([type="hidden"])')?.focus({ preventScroll: true });
        });
    });

    document.querySelectorAll('[data-phone]').forEach(initPhone);
    document.querySelectorAll('[data-password-toggle]').forEach(initPasswordToggle);
    document.querySelectorAll('[data-password-strength]').forEach(initStrength);
    document.querySelectorAll('form[data-validate]').forEach(initForm);

    // Если сервер вернул ошибки — фокус на первое поле с ошибкой
    document.querySelector('.input.is-invalid, .textarea.is-invalid')?.focus();
})();
