import { writable } from 'svelte/store';

export const toasts = writable([]);
// item: { id, type: 'success'|'danger'|'warning'|'info', text, ttl }

export function addToast(type, text, ttl = 2500) {
    const id = crypto.randomUUID?.() || String(Date.now() + Math.random());
    const item = { id, type, text, ttl };
    toasts.update((arr) => [...arr, item]);
    setTimeout(() => {
        toasts.update((arr) => arr.filter((x) => x.id !== id));
    }, ttl);
}

export const toast = {
    success: (t, ms) => addToast('success', t, ms),
    danger: (t, ms) => addToast('danger', t, ms),
    warning: (t, ms) => addToast('warning', t, ms),
    info: (t, ms) => addToast('info', t, ms)
};
