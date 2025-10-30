const BASE = import.meta.env.VITE_API_BASE || 'http://127.0.0.1:8000/api/v1';

export async function fetchJson(path, opts = {}) {
    const url = `${BASE}${path}`;
    let res;
    try {
        res = await fetch(url, {
            headers: { 'Content-Type': 'application/json', ...(opts.headers || {}) },
            ...opts
        });
    } catch (e) {
        return { ok: false, error: 'NETWORK_ERROR', detail: String(e) };
    }
    let data = null;
    try { data = await res.json(); } catch {}
    if (!res.ok) return { ok: false, status: res.status, error: data?.message || 'API_ERROR', raw: data };
    return { ok: true, data: data?.data ?? data ?? null };
}
