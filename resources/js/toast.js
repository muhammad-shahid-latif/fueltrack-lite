/**
 * Floating notifications. Server flash messages are rendered into the same
 * stack (see partials/flash), so feedback looks identical for page and AJAX actions.
 */
const ICONS = {
    success: 'check-circle-fill',
    warning: 'exclamation-triangle-fill',
    danger: 'x-octagon-fill',
    info: 'info-circle-fill',
};

const AUTO_HIDE_MS = { success: 4500, info: 4500, warning: 8000, danger: 9000 };

function stack() {
    let el = document.getElementById('toast-stack');

    if (!el) {
        el = document.createElement('div');
        el.id = 'toast-stack';
        el.className = 'toast-stack';
        el.setAttribute('aria-live', 'polite');
        document.body.appendChild(el);
    }

    return el;
}

function dismiss(toast) {
    if (!toast.isConnected || toast.classList.contains('is-leaving')) {
        return;
    }

    toast.classList.add('is-leaving');
    toast.addEventListener('animationend', () => toast.remove(), { once: true });
    setTimeout(() => toast.remove(), 400);
}

/** Wires dismiss buttons and the auto-hide timer of a toast element. */
export function arm(toast) {
    if (toast.dataset.armed) {
        return;
    }

    toast.dataset.armed = '1';
    toast.querySelector('[data-toast-close]')?.addEventListener('click', () => dismiss(toast));

    const type = toast.dataset.type ?? 'info';
    let timer = setTimeout(() => dismiss(toast), AUTO_HIDE_MS[type] ?? 5000);

    // Hold the message while the pointer is on it.
    toast.addEventListener('pointerenter', () => clearTimeout(timer));
    toast.addEventListener('pointerleave', () => (timer = setTimeout(() => dismiss(toast), 2500)));
}

export function toast(message, type = 'success') {
    const el = document.createElement('div');
    el.className = `app-toast app-toast-${type}`;
    el.dataset.type = type;
    el.setAttribute('role', type === 'danger' ? 'alert' : 'status');

    const icon = document.createElement('i');
    icon.className = `bi bi-${ICONS[type] ?? ICONS.info}`;

    const text = document.createElement('div');
    text.className = 'flex-grow-1';
    text.textContent = message;

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'btn-close btn-close-sm';
    close.setAttribute('aria-label', 'Close');
    close.dataset.toastClose = '';

    el.append(icon, text, close);
    stack().appendChild(el);
    arm(el);

    return el;
}

/** Arms toasts that came with the page (server flash messages). */
export function armAll(root = document) {
    root.querySelectorAll('.app-toast').forEach(arm);
}
