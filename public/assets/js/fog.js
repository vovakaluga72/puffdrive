/* ==================================================================
   «Сухой туман» на Canvas — лёгкий фоновый эффект.

   Как это работает: один раз рисуем мягкое размытое «облако» (радиальный
   градиент) во внеэкранный canvas, а затем каждый кадр просто копируем
   его десятки раз с разной прозрачностью и смещением. Это очень дёшево
   для процессора, поэтому эффект работает и на слабых телефонах.

   - рендер в половинном разрешении (туман размытый, детали не нужны);
   - анимация ставится на паузу, если canvas не виден или вкладка скрыта;
   - при prefers-reduced-motion рисуется один статичный кадр.

   Использование: <canvas data-fog data-fog-density="1"></canvas>
   ================================================================== */
(() => {
    'use strict';

    const PD = (window.PD = window.PD || {});

    /** Внеэкранная текстура одного облака заданного цвета. */
    function makeSprite(r, g, b) {
        const size = 256;
        const sprite = document.createElement('canvas');
        sprite.width = sprite.height = size;
        const ctx = sprite.getContext('2d');
        const gradient = ctx.createRadialGradient(size / 2, size / 2, 0, size / 2, size / 2, size / 2);
        gradient.addColorStop(0, `rgba(${r}, ${g}, ${b}, 1)`);
        gradient.addColorStop(0.35, `rgba(${r}, ${g}, ${b}, .55)`);
        gradient.addColorStop(0.7, `rgba(${r}, ${g}, ${b}, .15)`);
        gradient.addColorStop(1, `rgba(${r}, ${g}, ${b}, 0)`);
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, size, size);
        return sprite;
    }

    const SPRITES = {
        smoke: null, // холодный белёсый туман
        gold: null,  // тёплый отблеск шампань
    };

    class Fog {
        constructor(canvas) {
            this.canvas = canvas;
            this.ctx = canvas.getContext('2d', { alpha: true });
            this.density = Math.min(2, Math.max(0.2, parseFloat(canvas.dataset.fogDensity || '1')));
            this.scale = 0.5;              // половинное разрешение
            this.particles = [];
            this.running = false;
            this.visible = true;
            this.last = 0;
            this.pointer = { x: 0, y: 0, tx: 0, ty: 0 };
            this.reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            SPRITES.smoke ??= makeSprite(214, 220, 230);
            SPRITES.gold ??= makeSprite(222, 190, 130);

            this.frame = this.frame.bind(this);
            this.onPointer = this.onPointer.bind(this);

            this.resize();
            this.resizeObserver = new ResizeObserver(() => this.resize());
            this.resizeObserver.observe(canvas);

            // Анимируем только видимый canvas
            this.io = new IntersectionObserver(([entry]) => {
                this.visible = entry.isIntersecting;
                this.visible ? this.start() : this.stop();
            });
            this.io.observe(canvas);

            this.onVisibility = () => (document.hidden ? this.stop() : this.visible && this.start());
            document.addEventListener('visibilitychange', this.onVisibility);
            window.addEventListener('pointermove', this.onPointer, { passive: true });

            if (this.reduced) {
                this.draw(0);
            }
        }

        resize() {
            const rect = this.canvas.getBoundingClientRect();
            const w = Math.max(1, Math.round(rect.width * this.scale));
            const h = Math.max(1, Math.round(rect.height * this.scale));
            if (w === this.canvas.width && h === this.canvas.height && this.particles.length) return;

            this.canvas.width = w;
            this.canvas.height = h;
            this.width = w;
            this.height = h;

            // Количество облаков зависит от площади (в CSS-пикселях) и плотности
            const area = rect.width * rect.height;
            const count = Math.round(Math.min(46, Math.max(10, (area / 42000) * this.density)));
            this.particles = Array.from({ length: count }, () => this.createParticle(true));

            if (this.reduced) this.draw(0);
        }

        createParticle(randomX) {
            const s = this.scale;
            const radius = (120 + Math.random() * 260) * s * (0.7 + this.density * 0.3);
            return {
                x: randomX ? Math.random() * this.width : -radius,
                y: this.height * (0.15 + Math.random() * 0.95),
                r: radius,
                vx: (0.04 + Math.random() * 0.16) * s,      // медленный дрейф вправо
                vy: (Math.random() - 0.62) * 0.05 * s,       // и чуть вверх — туман поднимается
                alpha: 0.035 + Math.random() * 0.075,
                phase: Math.random() * Math.PI * 2,
                speed: 0.0002 + Math.random() * 0.0004,
                sprite: Math.random() < 0.22 ? SPRITES.gold : SPRITES.smoke,
                depth: 0.3 + Math.random() * 0.7,          // для параллакса
            };
        }

        onPointer(e) {
            // Лёгкий параллакс от курсора (±12px)
            this.pointer.tx = (e.clientX / window.innerWidth - 0.5) * 24 * this.scale;
            this.pointer.ty = (e.clientY / window.innerHeight - 0.5) * 24 * this.scale;
        }

        start() {
            if (this.running || this.reduced || document.hidden) return;
            this.running = true;
            this.last = performance.now();
            requestAnimationFrame(this.frame);
        }

        stop() {
            this.running = false;
        }

        frame(now) {
            if (!this.running) return;
            const dt = Math.min(64, now - this.last); // защита от скачка после паузы
            this.last = now;
            this.update(dt);
            this.draw(now);
            requestAnimationFrame(this.frame);
        }

        update(dt) {
            const p = this.pointer;
            p.x += (p.tx - p.x) * 0.03;
            p.y += (p.ty - p.y) * 0.03;

            for (let i = 0; i < this.particles.length; i++) {
                const part = this.particles[i];
                part.x += part.vx * dt * 0.06;
                part.y += part.vy * dt * 0.06;
                // Облако ушло за край — возвращаем слева
                if (part.x - part.r > this.width || part.y + part.r < 0) {
                    this.particles[i] = this.createParticle(false);
                }
            }
        }

        draw(now) {
            const { ctx } = this;
            ctx.clearRect(0, 0, this.width, this.height);
            ctx.globalCompositeOperation = 'lighter';

            for (const part of this.particles) {
                // «Дыхание» — плавное изменение плотности облака
                const pulse = 0.65 + 0.35 * Math.sin(now * part.speed + part.phase);
                ctx.globalAlpha = part.alpha * pulse;
                const size = part.r * 2;
                ctx.drawImage(
                    part.sprite,
                    part.x - part.r + this.pointer.x * part.depth,
                    part.y - part.r + this.pointer.y * part.depth,
                    size,
                    size * 0.72 // облака приплюснуты по вертикали
                );
            }
            ctx.globalAlpha = 1;
            ctx.globalCompositeOperation = 'source-over';
        }

        destroy() {
            this.stop();
            this.resizeObserver.disconnect();
            this.io.disconnect();
            document.removeEventListener('visibilitychange', this.onVisibility);
            window.removeEventListener('pointermove', this.onPointer);
        }
    }

    PD.Fog = Fog;
    PD.fogs = new Map();

    const supported = 'ResizeObserver' in window && 'IntersectionObserver' in window;
    if (supported) {
        document.querySelectorAll('canvas[data-fog]').forEach((canvas) => {
            PD.fogs.set(canvas, new Fog(canvas));
        });
    }
})();
