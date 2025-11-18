<script>
    import { onMount } from 'svelte';
    import { fetchJson } from '$lib/api/client.js';
    import { toast } from '$lib/stores/toast.js';
    import SearchBar from '$lib/components/SearchBar.svelte';
    import ProductPickerModal from '$lib/components/ProductPickerModal.svelte';
    import { useQueryState } from '$lib/utils/queryState.js';

    // ── URL 쿼리 상태(검색/필터/페이지) 공통화
    const qsCtl = useQueryState(
        { q: '', filter: '', channel_id: '', page: 1, per_page: 20 },
        { asNumbers: ['page', 'per_page'] }
    );

    // 로컬 입력 바인딩 변수
    let q = '';
    let filter = '';     // '' | '1'(미매핑) | '0'(매핑됨)
    let channel_id = '';
    let page = 1;
    let perPage = 20;

    // 데이터
    let rows = [];
    let meta = { current_page: 1, last_page: 1, per_page: 20, total: 0 };
    let loading = false;

    // 모달
    let pickerOpen = false;
    let targetMapping = null;

    // 백필 busy
    let backfillBusyId = 0;

    // 시간 포맷
    const fmt = (dt) => {
        if (!dt) return '';
        const d = new Date(dt);
        if (Number.isNaN(d.getTime())) return String(dt).slice(0, 19);
        const p = (n) => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${p(d.getMonth()+1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`;
    };

    function syncFromQS() {
        const s = qsCtl.snapshot();
        q          = s.q ?? '';
        filter     = s.filter ?? '';
        channel_id = s.channel_id ?? '';
        page       = s.page ?? 1;
        perPage    = s.per_page ?? 20;
    }
    function writeQS(patch={}) {
        qsCtl.set({ q, filter, channel_id, page, per_page: perPage, ...patch });
        qsCtl.write();
    }

    function parseMappings(res) {
        if (Array.isArray(res?.data?.data)) {
            const pg = res?.data?.pagination ?? {};
            return {
                list: res.data.data,
                pg: {
                    current_page: Number(pg.current_page ?? 1),
                    last_page:    Number(pg.last_page ?? 1),
                    per_page:     Number(pg.per_page ?? perPage),
                    total:        Number(pg.total ?? res.data.data.length),
                }
            };
        }
        if (Array.isArray(res?.data)) {
            return { list: res.data, pg: { current_page: 1, last_page: 1, per_page: res.data.length, total: res.data.length } };
        }
        return { list: [], pg: { current_page: 1, last_page: 1, per_page: 0, total: 0 } };
    }

    async function load(p = page) {
        loading = true;
        const params = new URLSearchParams({ page: String(p), per_page: String(perPage) });
        if (q) params.set('q', q);
        if (filter !== '') params.set('unmapped', filter);
        if (channel_id) params.set('channel_id', channel_id);

        try {
            const res = await fetchJson(`/product-name-mappings?${params.toString()}`);
            const { list, pg } = parseMappings(res);
            rows = list;
            meta = pg;
            page = meta.current_page;
            writeQS({ page }); // 서버가 보정한 페이지 반영
        } catch (e) {
            toast.danger('목록 조회 실패');
            rows = [];
            meta = { current_page: 1, last_page: 1, per_page: 0, total: 0 };
        } finally {
            loading = false;
        }
    }

    const onSearch = () => { page = 1; writeQS(); load(1); };
    const onReset  = () => { q=''; filter=''; channel_id=''; page=1; perPage=20; writeQS(); load(1); };
    const changePage = (p) => { if (p<1 || p>meta.last_page) return; page = p; writeQS(); load(p); };

    const openPicker = (row) => { targetMapping = row; pickerOpen = true; };

    async function handlePicked(product) {
        if (!targetMapping) return;
        try {
            await fetchJson(`/product-name-mappings/${targetMapping.id}/assign`, {
                method: 'PUT',
                body: JSON.stringify({ product_id: Number(product.id) })
            });
            toast.success('매핑이 저장되었습니다.');
            await load(page);
        } catch (e) {
            toast.danger('매핑 저장 중 오류');
        } finally {
            targetMapping = null;
        }
    }

    async function unassign(row) {
        try {
            await fetchJson(`/product-name-mappings/${row.id}/unassign`, { method: 'PUT' });
            toast.info('매핑이 해제되었습니다.');
            await load(page);
        } catch (e) {
            toast.danger('해제 중 오류');
        }
    }

    async function backfill(row, mode = 'exact') {
        try {
            backfillBusyId = row.id;
            const res = await fetchJson(`/product-name-mappings/${row.id}/backfill`, {
                method: 'POST',
                body: JSON.stringify({ mode })
            });
            const updated = res.data?.updated ?? 0;
            const ts = res.data?.last_backfilled_at || new Date().toISOString();
            const idx = rows.findIndex(r => r.id === row.id);
            if (idx >= 0) rows[idx] = { ...rows[idx], last_backfilled_at: ts };
            toast.success(`백필 완료: ${updated}건`);
        } catch (e) {
            toast.danger('백필 중 오류');
        } finally {
            backfillBusyId = 0;
        }
    }

    onMount(() => { syncFromQS(); load(page); });
</script>

<svelte:head><title>상품명 맵핑 · OF Intranet</title></svelte:head>

<section class="section" aria-labelledby="title">
    <div class="container">
        <header class="mb-3">
            <h1 id="title" class="title is-4">
                <span class="material-icons" aria-hidden="true">link</span>&nbsp;상품명 맵핑
            </h1>
            <p class="subtitle is-6 has-text-grey">채널 상품명/옵션을 내부 상품에 연결하고, 필요 시 주문을 백필합니다.</p>
        </header>

        <div class="level" style="margin-bottom:.75rem;">
            <div class="level-left"></div>
            <div class="level-right" style="gap:.5rem; flex-wrap:wrap;">
                <SearchBar bind:q
                           filterOptions={[{ value:'', label:'전체' }, { value:'1', label:'미매핑만' }, { value:'0', label:'매핑됨만' }]}
                           bind:filter
                           order="select-input"
                           on:search={onSearch}
                           on:reset={onReset} />
                <span class="tag is-light">총 {meta.total}개</span>
            </div>
        </div>

        {#if loading}
            <div class="notification is-light">로딩 중…</div>
        {:else if !rows.length}
            <div class="notification is-light">데이터가 없습니다.</div>
        {:else}
            <div class="stack">
                {#each rows as row}
                    <article class="of-card" aria-label={`매핑 ${row.id}`}>
                        <div class="of-card__head">
                            <div class="chips">
                                <span class="chip"><span class="chip__icon material-icons">storefront</span>{row.channel_name ?? `CH ${row.channel_id}`}</span>
                                <span class="chip chip--ghost"><span class="chip__icon material-icons">tag</span># {row.id}</span>
                                {#if row.last_backfilled_at}
                                    <span class="chip chip--time" title={fmt(row.last_backfilled_at)}>
                                        <span class="chip__icon material-icons">history</span>{fmt(row.last_backfilled_at)}
                                    </span>
                                {/if}
                            </div>
                            <div class="meta-right">
                                {#if row.product_id}
                                    <span class="tag is-info is-light">{row.product_name}</span>
                                    {#if row.product_code}&nbsp;<code class="mono">{row.product_code}</code>{/if}
                                {:else}
                                    <span class="tag is-warning is-light">미매핑</span>
                                {/if}
                            </div>
                        </div>

                        <div class="of-card__body">
                            <div class="kv">
                                <div class="kv__icon material-icons">inventory_2</div>
                                <div class="kv__label">채널 상품명</div>
                                <div class="kv__value">{row.listing_title}</div>
                            </div>
                            <div class="kv">
                                <div class="kv__icon material-icons">tune</div>
                                <div class="kv__label">옵션명</div>
                                <div class="kv__value">{row.option_title ?? '-'}</div>
                            </div>
                            <div class="kv">
                                <div class="kv__icon material-icons">check_circle</div>
                                <div class="kv__label">현재 매핑</div>
                                <div class="kv__value">
                                    {#if row.product_id}
                                        <strong>{row.product_name}</strong>
                                        {#if row.product_code} · <code class="mono">{row.product_code}</code>{/if}
                                    {:else}-{/if}
                                </div>
                            </div>
                        </div>

                        <div class="of-card__foot">
                            <div class="foot-left text-dim is-size-7">
                                최근 백필:
                                {#if row.last_backfilled_at}
                                    <span class="mono">{fmt(row.last_backfilled_at)}</span>
                                {:else}
                                    <span class="has-text-grey-light">-</span>
                                {/if}
                            </div>
                            <div class="foot-right">
                                <button class="button is-light is-small icon-btn" title="새로고침" on:click={() => load(page)}>
                                    <span class="material-icons">refresh</span>
                                </button>
                                <button class="button is-small is-primary" on:click={() => openPicker(row)}>
                                    {row.product_id ? '변경' : '매핑'}
                                </button>
                                {#if row.product_id}
                                    <button class="button is-small" on:click={() => unassign(row)}>해제</button>
                                    <button class={"button is-small is-info" + (backfillBusyId===row.id ? " is-loading": "")}
                                            title="주문 백필(정확 매칭)" on:click={() => backfill(row, 'exact')}
                                            disabled={backfillBusyId===row.id}>백필</button>
                                {/if}
                            </div>
                        </div>
                    </article>
                {/each}
            </div>
        {/if}

        <div class="is-flex is-justify-content-space-between is-align-items-center mt-4">
            <span class="tag is-light">총 {meta.total}개</span>
            <nav class="pagination is-small">
                <button class="pagination-previous" on:click={() => changePage(meta.current_page-1)} disabled={meta.current_page<=1}>이전</button>
                <button class="pagination-next" on:click={() => changePage(meta.current_page+1)} disabled={meta.current_page>=meta.last_page}>다음</button>
                <ul class="pagination-list">
                    <li><span class="pagination-link is-current">{meta.current_page}/{meta.last_page}</span></li>
                </ul>
            </nav>
        </div>
    </div>
</section>

<ProductPickerModal bind:open={pickerOpen} {targetMapping}
                    onClose={() => { targetMapping = null; }}
                    onPicked={handlePicked} />

<style>
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "D2Coding", "Noto Sans Mono CJK", monospace; }
    .text-dim { color:#6b7280; }
    .stack { display:flex; flex-direction:column; gap:1rem; }
    .of-card { border:1px solid #eff1f5; border-radius:14px; background:#fff; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.04); }
    .of-card__head, .of-card__foot { display:flex; align-items:center; justify-content:space-between; padding:.75rem 1rem; background:linear-gradient(180deg,#fafbfd,#f7f8fb); }
    .of-card__body { padding:.5rem 1rem .75rem; display:grid; grid-template-columns:1fr; gap:.4rem; }
    .chips { display:flex; gap:.5rem; align-items:center; flex-wrap:wrap; }
    .chip { display:inline-flex; align-items:center; gap:.3rem; padding:.25rem .6rem; border-radius:999px; background:#eef2ff; color:#3949ab; font-weight:600; font-size:.8rem; }
    .chip__icon { font-size:18px; }
    .chip--ghost { background:#f1f5f9; color:#334155; }
    .chip--time { background:#e8f5e9; color:#2e7d32; }
    .kv { display:grid; grid-template-columns:20px 140px 1fr; align-items:flex-start; gap:.75rem; padding:.35rem 0; border-bottom:1px dashed #eef2f6; }
    .kv:last-child { border-bottom:none; }
    .kv__icon { font-size:18px; color:#94a3b8; text-align:center; padding-top:.2rem; }
    .kv__label { color:#6b7280; font-weight:600; }
    .kv__value { min-width:0; }
    .icon-btn { padding:0 8px; height:32px; display:inline-flex; align-items:center; justify-content:center; }
    .icon-btn .material-icons { font-size:20px; }
    .of-card__foot { gap:.75rem; }
    .of-card__foot .foot-left { display:flex; align-items:center; gap:.5rem; }
    .of-card__foot .foot-right { display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; }
</style>
