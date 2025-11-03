// 공통 fetch 래퍼: /api/v1 기준, JSON 응답 표준화
const API_BASE = import.meta.env.VITE_API_BASE?.replace(/\/+$/, '') || 'http://127.0.0.1:8000/api/v1';

export async function fetchJson(path, { method = 'GET', headers = {}, body } = {}) {
    const url = path.startsWith('http') ? path : `${API_BASE}${path.startsWith('/') ? '' : '/'}${path}`;
    const init = {
        method,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...headers
        },
        body
    };
    if (method === 'GET' || method === 'HEAD') delete init.body;

    let res;
    try {
        res = await fetch(url, init);
    } catch (err) {
        return {
            ok: false,
            status: 0,
            code: 'network_error',
            error: '네트워크 오류가 발생했습니다.',
            rawError: err
        };
    }

    // API는 항상 JSON
    let data;
    try {
        data = await res.json();
    } catch {
        data = null;
    }

    // 백엔드 ApiResponse 규격 지원
    if (data && typeof data === 'object' && ('ok' in data)) {
        return data;
    }

    // 방어: 라라벨 기본 응답 등 비표준의 경우
    if (res.ok) {
        return { ok: true, status: res.status, data };
    }

    return {
        ok: false,
        status: res.status,
        code: data?.code || 'server_error',
        error: data?.message || '서버 오류가 발생했습니다.',
        errors: data?.errors || null
    };
}

// GET 쿼리 헬퍼
export function qs(params = {}) {
    const s = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
        if (v === undefined || v === null || v === '') return;
        s.append(k, String(v));
    });
    const q = s.toString();
    return q ? `?${q}` : '';
}
