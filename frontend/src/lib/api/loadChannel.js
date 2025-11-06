// 공통: 채널 메타 로더 (캐시 + 중복요청 방지)
import { fetchJson } from '$lib/api/client.js';

const cache = new Map();     // id -> data
const inflight = new Map();  // id -> Promise

export async function loadChannelMeta(channelId, { force = false } = {}) {
    const id = Number(channelId);
    if (!id) return null;

    if (!force && cache.has(id)) return cache.get(id);
    if (inflight.has(id)) return inflight.get(id);

    const p = (async () => {
        try {
            const res = await fetchJson(`/channels/${id}`);
            const data = res.ok ? (res.data ?? null) : null;
            cache.set(id, data);
            return data;
        } finally {
            inflight.delete(id);
        }
    })();

    inflight.set(id, p);
    return p;
}

export function refreshChannelMeta(channelId) {
    return loadChannelMeta(channelId, { force: true });
}

export function forgetChannelMeta(channelId) {
    cache.delete(Number(channelId));
}
