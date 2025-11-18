<script>
    import Modal from '$lib/components/Modal.svelte';
    import { fetchJson } from '$lib/api/client.js';
    import { toast } from '$lib/stores/toast.js';

    export let open = false;                // 부모에서 bind
    export let targetMapping = null;        // { id, listing_title, option_title, ... }
    export let onClose = () => {};
    export let onPicked = async (product) => {};

    // 검색/페이지 상태
    let q = '';
    let page = 1;
    let perPage = 20;
    let loading = false;

    let items = [];
    let meta = { current_page: 1, last_page: 1, per_page: 20, total: 0 };

    // ✅ 어떤 응답 포맷이 와도 리스트/페이지가 나오도록 방어적 파싱
    function parseList(res) {
        // 당신 서버 포맷: { ok, data: { items:[...], pagination:{...} } }
        if (Array.isArray(res?.data?.items)) {
            return {
                list: res.data.items,
                meta: normalizePg(res.data.pagination || {})
            };
        }
        // 일반 리소스 포맷: { ok, data: { data:[...], pagination:{...} } }
        if (Array.isArray(res?.data?.data)) {
            return {
                list: res.data.data,
                meta: normalizePg(res.data.pagination || {})
            };
        }
        // 간단 배열: { ok, data:[...] } 또는 [...]
        if (Array.isArray(res?.data)) return { list: res.data, meta: null };
        if (Array.isArray(res))       return { list: res,     meta: null };
        return { list: [], meta: null };
    }


    function normalizePg(pg) {
        return {
            current_page: Number(pg.current_page ?? 1),
            last_page:    Number(pg.last_page ?? 1),
            per_page:     Number(pg.per_page ?? 0),
            total:        Number(pg.total ?? 0),
        };
    }


    async function load() {
        if (!open) return;
        loading = true;

        const params = new URLSearchParams({
            page: String(page),
            per_page: String(perPage)
        });
        if (q) params.set('q', q);

        try {
            const res = await fetchJson(`/products?${params.toString()}`);
            const { list, meta: m } = parseList(res);
            items = list;
            meta = m ?? { current_page: 1, last_page: 1, per_page: list.length, total: list.length };
        } catch (e) {
            items = [];
            meta = { current_page: 1, last_page: 1, per_page: 0, total: 0 };
        } finally {
            loading = false;
        }
    }

    // 모달이 열릴 때: 기본 검색어를 채우되, "전체보기"도 바로 되도록 load()는 무조건 호출
    $: if (open) {
        page = 1;
        perPage = 20;
        q = (targetMapping?.listing_title ?? '').trim(); // 자동 제안
        // 바로 전체 로드 (q는 있어도 되고 없어도 됨)
        queueMicrotask(load);
    }

    function close() {
        open = false;
        onClose?.();
    }

    async function pick(product) {
        await onPicked?.(product);
        close();
    }
</script>

<!-- ✅ on:close 훅으로 X 버튼/ESC 닫기 보장 -->
<Modal bind:open on:close={close}
       title="상품 선택"
       ariaDescription="상품을 검색하고 선택하여 매핑합니다."
       width="960px" maxHeight="80vh" draggable>
    <svelte:fragment slot="body">
        <div class="box" style="margin-bottom:.75rem;">
            <div class="fields is-grouped is-align-items-center" style="display:flex;gap:.75rem;flex-wrap:wrap;">
                <div class="control is-expanded">
                    <input class="input" placeholder="상품명/코드 검색"
                           bind:value={q}
                           on:keydown={(e)=> e.key==='Enter' && (page=1, load())}
                           aria-label="상품 검색어"/>
                </div>
                <div class="control">
                    <div class="select">
                        <select bind:value={perPage} on:change={() => (page=1, load())} aria-label="페이지당">
                            <option value="10">10개</option>
                            <option value="20">20개</option>
                            <option value="50">50개</option>
                            <option value="100">100개</option>
                        </select>
                    </div>
                </div>
                <div class="control">
                    <button class="button is-primary" on:click={() => (page=1, load())}>
                        <span class="material-icons" aria-hidden="true">search</span>&nbsp;검색
                    </button>
                </div>
            </div>
            {#if targetMapping}
                <p class="is-size-7 has-text-grey">
                    대상 채널상품: <strong>{targetMapping.listing_title}</strong>
                    {#if targetMapping.option_title}&nbsp;<span class="tag is-light">{targetMapping.option_title}</span>{/if}
                </p>
            {/if}
        </div>

        <div class="table-container">
            <table class="table is-fullwidth is-striped is-hoverable">
                <thead>
                <tr>
                    <th style="width:90px;">ID</th>
                    <th>상품명</th>
                    <th style="width:160px;">코드</th>
                    <th style="width:120px;" class="has-text-right">작업</th>
                </tr>
                </thead>
                <tbody>
                {#if loading}
                    <tr><td colspan="4">로딩 중…</td></tr>
                {:else if !items.length}
                    <tr><td colspan="4">검색 결과가 없습니다.</td></tr>
                {:else}
                    {#each items as p}
                        <tr>
                            <td>{p.id}</td>
                            <td>{p.name}</td>
                            <td><code class="mono">{p.code}</code></td>
                            <td class="has-text-right">
                                <button class="button is-small is-primary" on:click={() => pick(p)}>선택</button>
                            </td>
                        </tr>
                    {/each}
                {/if}
                </tbody>
            </table>
        </div>

        <nav class="pagination is-centered" role="navigation" aria-label="pagination" style="margin-top:.75rem;">
            <a class="pagination-previous"
               on:click={() => { if (meta.current_page > 1) { page = meta.current_page - 1; load(); } }}>이전</a>
            <a class="pagination-next"
               on:click={() => { if (meta.current_page < meta.last_page) { page = meta.current_page + 1; load(); } }}>다음</a>
            <ul class="pagination-list">
                <li><span class="pagination-link is-current">{meta.current_page} / {meta.last_page} · 총 {meta.total}</span></li>
            </ul>
        </nav>
    </svelte:fragment>

    <svelte:fragment slot="footer">
        <div class="actions-gap" style="display:flex;gap:.75rem;flex-wrap:wrap;">
            <button class="button" on:click={close}>닫기</button>
        </div>
    </svelte:fragment>
</Modal>