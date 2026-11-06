/* ==================================================================
   Главная страница: подсветка карточек категорий за курсором
   и навигация слайдера отзывов.
   ================================================================== */
(() => {
    'use strict';

    // Мягкое золотое пятно, следующее за курсором по карточке категории
    document.querySelectorAll('.category-card').forEach((card) => {
        card.addEventListener('pointermove', (e) => {
            const rect = card.getBoundingClientRect();
            card.style.setProperty('--mx', `${e.clientX - rect.left}px`);
            card.style.setProperty('--my', `${e.clientY - rect.top}px`);
        });
    });

    // Слайдер отзывов: кнопки «назад/вперёд» прокручивают на одну карточку
    document.querySelectorAll('[data-slider]').forEach((track) => {
        const nav = document.querySelector(`[data-slider-nav="${track.dataset.slider}"]`);
        if (!nav) return;
        const prev = nav.querySelector('[data-slider-prev]');
        const next = nav.querySelector('[data-slider-next]');

        const step = () => {
            const card = track.firstElementChild;
            const gap = parseFloat(getComputedStyle(track).columnGap) || 0;
            return card ? card.getBoundingClientRect().width + gap : track.clientWidth;
        };
        const update = () => {
            const max = track.scrollWidth - track.clientWidth - 2;
            prev.disabled = track.scrollLeft <= 2;
            next.disabled = track.scrollLeft >= max;
            nav.hidden = track.scrollWidth <= track.clientWidth + 2;
        };

        prev.addEventListener('click', () => track.scrollBy({ left: -step(), behavior: 'smooth' }));
        next.addEventListener('click', () => track.scrollBy({ left: step(), behavior: 'smooth' }));
        track.addEventListener('scroll', () => requestAnimationFrame(update), { passive: true });
        window.addEventListener('resize', update);
        update();
    });
})();
