// src/lib/utils/queryState.js
// useQueryState - URL 쿼리와 상태를 동기화하는 간단한 유틸
//
// 사용 예:
//   import { useQueryState } from '$lib/utils/queryState.js';
//   const { state, set, write, reset } = useQueryState({ q: '', page: 1, per_page: 20 });
//   // in component:
//   $: local = $state; // 구독
//   set({ page: 2 }); write(); // 메모리 갱신 + URL 갱신
//
// 동작 원리:
//  - 초기값: URL에 있는 값 우선, 없으면 defaults 사용
//  - set(patch) : 상태를 병합(메모리만 변경)
//  - write() : 현재 상태를 URL에 반영 (replaceState 사용)
//  - reset() : defaults로 초기화 + URL 반영
//
// 옵션:
//  - asNumbers: 배열로 넘기면 해당 키들을 숫자로 파싱함 (예: ['page','per_page'])
//  - namespace: 쿼리 키 접두사 사용시(안 쓰면 그대로 키 사용)

import { writable, readable } from 'svelte/store';

const isBrowser = typeof window !== 'undefined' && typeof URLSearchParams !== 'undefined';

function readUrlParams(namespace, defaults) {
    if (!isBrowser) return { ...defaults };

    const sp = new URLSearchParams(window.location.search);
    const out = { ...defaults };

    for (const k of Object.keys(defaults)) {
        const key = namespace ? `${namespace}[${k}]` : k;
        if (!sp.has(key)) continue;
        out[k] = sp.get(key);
    }
    return out;
}

function writeUrlParams(namespace, obj) {
    if (!isBrowser) return;

    const url = new URL(window.location.href);
    const sp = new URLSearchParams(url.search);

    for (const [k, v] of Object.entries(obj)) {
        const key = namespace ? `${namespace}[${k}]` : k;
        if (v === '' || v === null || v === undefined) {
            sp.delete(key);
        } else {
            sp.set(key, String(v));
        }
    }

    // replaceState로 히스토리 오염 방지
    url.search = sp.toString();
    window.history.replaceState({}, '', url);
}

/**
 * useQueryState(defaults, opts)
 * defaults: { q:'', page:1, per_page:20, ... }
 * opts: { asNumbers: ['page','per_page'], namespace: '' }
 */
export function useQueryState(defaults = {}, opts = {}) {
    const namespace = opts.namespace || '';
    const asNumbers = Array.isArray(opts.asNumbers) ? opts.asNumbers : [];

    // 초기값: URL -> defaults
    const initialRaw = readUrlParams(namespace, defaults);

    // 타입 보정: 숫자 필드 등
    const normalize = (obj) => {
        const out = { ...obj };
        for (const k of asNumbers) {
            if (out[k] === undefined || out[k] === null || out[k] === '') continue;
            const n = Number(out[k]);
            out[k] = Number.isNaN(n) ? out[k] : n;
        }
        return out;
    };

    const initial = normalize(initialRaw);

    const store = writable(initial);
    let current = initial;
    const unsub = store.subscribe((v) => (current = v));

    return {
        // Svelte에서 $state로 사용
        state: isBrowser ? store : readable(initial),

        // 부분 업데이트(메모리만)
        set(patch) {
            store.update((prev) => normalize({ ...prev, ...patch }));
        },

        // 전체 덮어쓰기
        replace(obj) {
            store.set(normalize({ ...obj }));
        },

        // 현재 상태를 URL에 기록 (namespace 적용)
        write() {
            // 문자열로 쓰기: 숫자/문자 그대로 저장
            const toWrite = {};
            for (const [k, v] of Object.entries(current)) toWrite[k] = v;
            writeUrlParams(namespace, toWrite);
        },

        // defaults로 초기화 + URL 기록
        reset() {
            store.set(normalize({ ...defaults }));
            writeUrlParams(namespace, defaults);
        },

        // helper to destroy internal subscription when needed
        destroy() {
            unsub();
        },

        // expose current snapshot (편의)
        snapshot() {
            return current;
        }
    };
}

export default { useQueryState };
