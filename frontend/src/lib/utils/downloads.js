// 단일 책임: "요청 → Blob/Text → 로컬 파일 저장" + 경로/쿼리 정리 + 파일명 추출 + 타임스탬프 유틸

/**
 * 파일명 추출: Content-Disposition 지원 (filename*, filename)
 */
function filenameFromContentDisposition(res, fallback = 'download') {
    const cd = res.headers?.get?.('Content-Disposition') || '';
    // RFC 5987: filename*=UTF-8''encoded
    const star = cd.match(/filename\*\s*=\s*UTF-8''([^;]+)/i);
    if (star && star[1]) {
        try {
            return decodeURIComponent(star[1].replace(/"/g, '').trim());
        } catch (_) {
            // noop → 아래 일반 filename으로 폴백
        }
    }
    const normal = cd.match(/filename\s*=\s*("?)([^";]+)\1/i);
    if (normal && normal[2]) {
        return normal[2].trim();
    }
    return fallback;
}

/**
 * 쿼리 객체 → URLSearchParams 붙이기
 */
function appendQuery(url, params) {
    if (!params || typeof params !== 'object') return url;
    const u = new URL(url, typeof window !== 'undefined' ? window.location.origin : 'http://localhost');
    const sp = new URLSearchParams(u.search);
    Object.entries(params).forEach(([k, v]) => {
        if (v === undefined || v === null || v === '') return;
        sp.set(k, String(v));
    });
    u.search = sp.toString();
    return u.toString();
}

/**
 * 상대/절대 경로 안전 합치기
 */
function resolveUrl(pathOrUrl) {
    const base = (import.meta.env.VITE_API_BASE || '').replace(/\/+$/, '');
    const isAbsolute = /^https?:\/\//i.test(pathOrUrl);
    if (isAbsolute) return pathOrUrl;
    const path = String(pathOrUrl || '');
    if (!path) return base || '/';
    // path가 '/xxx'로 시작하지 않으면 붙여줌
    const tail = path.startsWith('/') ? path : `/${path}`;
    return `${base}${tail}`;
}

/**
 * Blob을 파일로 저장
 */
export async function downloadBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename || 'download';
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
}

/**
 * 텍스트를 파일로 저장
 */
export async function downloadText(text, filename, mime = 'text/plain;charset=utf-8') {
    const blob = new Blob([text ?? ''], { type: mime });
    return downloadBlob(blob, filename);
}

/**
 * API/URL에서 받아서 저장
 *
 * @param {string} pathOrUrl  '/orders/export' 또는 절대 URL
 * @param {object} options
 *   - filename: 기본 파일명(헤더 없을 때)
 *   - params:   쿼리 객체 {a:1, b:'x'}
 *   - method:   기본 'GET'
 *   - headers:  추가 헤더
 *   - body:     fetch body
 *   - response: 'blob' | 'text' (기본 blob)
 *   - cors:     true(default) → fetch mode: 'cors'
 *   - withCredentials: false(default). true면 credentials: 'include'
 */
export async function downloadUrl(pathOrUrl, options = {}) {
    const {
        filename = 'download',
        params = undefined,
        method = 'GET',
        headers = {},
        body = undefined,
        response = 'blob', // 'blob' | 'text'
        cors = true,
        withCredentials = false,
    } = options;

    const resolved = resolveUrl(pathOrUrl);
    const url = appendQuery(resolved, params);

    const res = await fetch(url, {
        method,
        headers,
        body,
        mode: cors ? 'cors' : 'same-origin',
        credentials: withCredentials ? 'include' : 'omit',
        // 파일 다운로드는 referrer 불필요 → 일부 환경에서 보안 경고 방지
        referrerPolicy: 'no-referrer',
    });

    if (!res.ok) {
        let detail = '';
        try { detail = await res.text(); } catch {}
        throw new Error(`다운로드 실패(${res.status})${detail ? ' - ' + detail : ''}`);
    }

    // 서버가 제공하는 파일명 우선
    const name = filenameFromContentDisposition(res, filename);

    if (response === 'text') {
        const text = await res.text();
        return downloadText(text, name);
    }

    const blob = await res.blob();
    return downloadBlob(blob, name);
}

/**
 * 파일명에 붙이기 좋은 타임스탬프 (YYYYMMDD-HHMMSS)
 */
export function timestamp() {
    const d = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return [
        d.getFullYear(),
        pad(d.getMonth() + 1),
        pad(d.getDate()),
        '-',
        pad(d.getHours()),
        pad(d.getMinutes()),
        pad(d.getSeconds()),
    ].join('');
}
