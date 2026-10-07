/**
 * Gentle 3D tilt toward the pointer for elements marked [data-tilt].
 * The value of the attribute is the maximum angle in degrees (default 6).
 *
 * Small display cards (no tables or form fields inside) get a subtle tilt
 * automatically, so the whole interface feels physical.
 * Skipped on touch screens and when the user prefers reduced motion.
 */
const AUTO_TILT_SELECTOR = '.app-main .card:not([data-tilt]):not(.no-tilt)';
const AUTO_TILT_MAX_HEIGHT = 380;

function autoTilt(root) {
    root.querySelectorAll(AUTO_TILT_SELECTOR).forEach((card) => {
        if (card.querySelector('table, form, input, select, textarea, canvas, .tank-3d') || card.offsetHeight > AUTO_TILT_MAX_HEIGHT) {
            return;
        }

        card.dataset.tilt = '3';
        card.classList.add('tilt-glare');
    });
}

export function initTilt(root = document) {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const finePointer = window.matchMedia('(pointer: fine)').matches;

    if (reduceMotion || !finePointer) {
        return;
    }

    autoTilt(root);

    root.querySelectorAll('[data-tilt]').forEach((el) => {
        if (el.dataset.tiltReady) {
            return;
        }

        el.dataset.tiltReady = '1';
        const max = Number(el.dataset.tilt) || 6;

        el.addEventListener('pointermove', (event) => {
            const box = el.getBoundingClientRect();
            const x = (event.clientX - box.left) / box.width - 0.5;
            const y = (event.clientY - box.top) / box.height - 0.5;

            el.classList.add('is-tilting');
            el.style.setProperty('--ry', `${(x * max * 2).toFixed(2)}deg`);
            el.style.setProperty('--rx', `${(-y * max * 2).toFixed(2)}deg`);
            el.style.setProperty('--gx', `${((x + 0.5) * 100).toFixed(1)}%`);
            el.style.setProperty('--gy', `${((y + 0.5) * 100).toFixed(1)}%`);
        });

        el.addEventListener('pointerleave', () => {
            el.classList.remove('is-tilting');
            el.style.removeProperty('--rx');
            el.style.removeProperty('--ry');
        });
    });
}
