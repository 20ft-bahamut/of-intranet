<script>
    import { onMount } from 'svelte';
    import { fetchJson, qs } from '$lib/api/client.js';
    import ConfirmModal from '$lib/components/ConfirmModal.svelte';
    import NoteBadge from '$lib/components/NoteBadge.svelte';
    import Modal from '$lib/components/Modal.svelte';
    import SearchBar from '$lib/components/SearchBar.svelte';
    import { toast } from '$lib/stores/toast.js';
    import { useQueryState } from '$lib/utils/queryState.js';

    // ── URL 쿼리 상태 통일 (이 파일만의 키들)
    const qsCtl = useQueryState(
        { q: '', onlyActive: '', page: 1, per_page: 20 },
        { asNumbers: ['page', 'per_page'] }
    );

    // 로컬 바인딩(입력 컴포넌트는 변수를 요구)
    let q = '';
    let onlyActive = '';
    let page = 1;
    let perPage = 20;

    // 목록 데이터
    let items = [];
    let loading = false;
    let error = null;
    let total = 0, lastPage = 1, from = 0, to = 0;

    // 삭제 확인
    let confirmOpen = false;
    let confirmTarget = null;
    let confirmBusy = false;

    // 등록/수정 모달
    let modalOpen = false;
    let editing = null;
    let firstInput;
    const emptyForm = () => ({ name:'', code:'', max_merge_qty:1, spec:'', description:'', is_active:true });
    let form = emptyForm();

    // ── helpers
    function syncFromQS() {
        const s = qsCtl.snapshot();
        q          = s.q ?? '';
        onlyActive = s.onlyActive ?? '';
        page       = s.page ?? 1;
        perPage    = s.per_page ?? 20;
    }
    function writeQS(patch={}) {
        qsCtl.set({ q, onlyActive, page, per_page: perPage, ...patch });
        qsCtl.write();
    }

    async function load() {
        loading = true; error = null;
        try {
            const res = await fetchJson('/products' + qs({
                q: q || undefined,
                is_active: onlyActive === '' ? undefined : onlyActive,
                page,
                per_page: perPage
            }));
            if (!res.ok) throw new Error(res.message || '불러오기 실패');

            const payload = res.data || {};
            items = Array.isArray(payload.items) ? payload.items : [];

            const pg = payload.pagination || {};
            total    = pg.total ?? items.length;
            lastPage = pg.last_page ?? 1;
            from     = pg.from ?? 0;
            to       = pg.to ?? 0;
        } catch (e) {
            error = e.message || String(e);
        } finally {
            loading = false;
        }
    }

    function goPage(n) {
        const next = Math.max(1, Math.min(n, lastPage || 1));
        if (next === page) return;
        page = next;
        writeQS();
        load();
    }
    const nextPage = () => goPage(page + 1);
    const prevPage = () => goPage(page - 1);

    // 검색바 이벤트
    function doSearch() {
        page = 1;
        writeQS();
        load();
    }
    function doReset() {
        q = ''; onlyActive = ''; page = 1; perPage = 20;
        writeQS();
        load();
    }

    // 모달
    function openNew() { editing = null; form = emptyForm(); modalOpen = true; queueMicrotask(()=>firstInput?.focus()); }
    function openEdit(row) {
        editing = row;
        form = {
            name: row.name ?? '',
            code: row.code ?? '',
            max_merge_qty: row.max_merge_qty ?? 1,
            spec: row.spec ?? '',
            description: row.description ?? '',
            is_active: !!row.is_active
        };
        modalOpen = true;
        queueMicrotask(()=>firstInput?.focus());
    }
    const closeModal = () => { modalOpen = false; editing = null; };

    async function save() {
        const payload = { ...form, max_merge_qty: parseInt(form.max_merge_qty || 1, 10) };
        try {
            const url = editing ? `/products/${editing.id}` : '/products';
            const method = editing ? 'PUT' : 'POST';
            const res = await fetchJson(url, { method, body: JSON.stringify(payload) });
            if (!res.ok) {
                const first = res.errors && Object.values(res.errors)[0]?.[0];
                toast.danger(first || res.message || '저장 실패');
                return;
            }
            toast.success(editing ? '상품을 수정했습니다.' : '상품을 등록했습니다.');
            closeModal();
            page = 1; writeQS(); await load();
        } catch (e) {
            toast.danger('저장 실패: ' + (e.message || String(e)));
        }
    }

    function askRemove(row) { confirmTarget = row; confirmOpen = true; }
    async function reallyRemove(row) {
        try {
            const res = await fetchJson(`/products/${row.id}`, { method: 'DELETE' });
            if (!res.ok) throw new Error(res.error || '삭제 실패');
            toast.success('삭제 완료');
            if (items.length === 1 && page > 1) page -= 1;
            writeQS(); await load();
        } catch (e) {
            toast.danger('삭제 실패: ' + (e.message || String(e)));
        }
    }

    async function toggleActive(row, checked) {
        try {
            const res = await fetchJson(`/products/${row.id}`, {
                method: 'PUT',
                body: JSON.stringify({
                    name: row.name, code: row.code, max_merge_qty: row.max_merge_qty,
                    spec: row.spec, description: row.description, is_active: !!checked
                })
            });
            if (!res.ok) throw new Error(res.error || '상태 변경 실패');
            toast.success(`활성 상태 변경: ${checked ? '활성' : '비활성'}`);
            await load();
        } catch (e) {
            toast.danger('상태 변경 실패: ' + (e.message || String(e)));
        }
    }

    async function copyProduct(row) {
        try {
            const show = await fetchJson(`/products/${row.id}`);
            if (!show.ok) throw new Error(show.message || '원본 조회 실패');
            const src = show.data ?? row;

            const baseCode = String(src.code || '').trim();
            if (!baseCode) throw new Error('원본 코드가 비어 있습니다.');

            const baseBody = {
                name: String(src.name || '').trim(),
                max_merge_qty: Number(src.max_merge_qty || 1),
                spec: src.spec ?? '',
                description: src.description ?? '',
                is_active: !!src.is_active,
            };

            let created = null;
            for (let i = 0; i < 50; i++) {
                const candidate = `${baseCode}_${i}`;
                const res = await fetchJson('/products', {
                    method: 'POST',
                    body: JSON.stringify({ ...baseBody, code: candidate })
                });
                if (res.ok) { created = res.data; break; }
                const status = res.status || 0, code = res.code || '';
                if (status === 409 || code === 'conflict') continue;
                throw new Error(res.message || `복사 실패(${status})`);
            }
            if (!created) throw new Error('사용 가능한 코드 후보를 찾지 못했습니다.');
            toast.success(`복사 완료: ${created.code}`);
            page = 1; writeQS(); await load();
        } catch (e) {
            toast.danger('복사 실패: ' + (e.message || String(e)));
        }
    }

    onMount(() => { syncFromQS(); load(); });
</script>

<svelte:head><title>상품 관리 · OF Intranet</title></svelte:head>

<section class="section" aria-labelledby="pageTitle">
    <div class="container">
        <header class="mb-4">
            <h1 id="pageTitle" class="title is-4">
                <span class="material-icons" aria-hidden="true">inventory_2</span>&nbsp;상품 관리
            </h1>
            <p class="subtitle is-6">상품명/코드/스펙/합포장/활성 등을 관리합니다.</p>
        </header>

        {#if error}
            <article class="message is-danger"><div class="message-body">{error}</div></article>
        {/if}

        <div class="level mb-3">
            <div class="level-left">
                <button class="button is-link" type="button" on:click={openNew}>
                    <span class="material-icons" aria-hidden="true">add_circle</span>&nbsp;새 상품 등록
                </button>
            </div>

            <div class="level-right" style="gap:.5rem; align-items:center;">
                <SearchBar
                        bind:q
                        placeholder="상품명 또는 코드로 검색"
                        filterOptions={[{ value: '1', label: '활성만' }, { value: '0', label: '비활성만' }]}
                        bind:filter={onlyActive}
                        order="input-select"
                        on:search={doSearch}
                        on:reset={doReset}
                />
                <span class="tag is-light">총 {total}개</span>
                {#if loading}<span class="tag is-info">불러오는 중…</span>{/if}
            </div>
        </div>

        <div class="table-container" role="region" aria-label="상품 목록">
            <table class="table is-fullwidth is-striped is-hoverable products-table">
                <caption class="is-sr-only">상품 목록</caption>
                <thead>
                <tr>
                    <th>순번</th>
                    <th>ID</th>
                    <th>상품명</th>
                    <th>코드</th>
                    <th>최대 합포장</th>
                    <th>스펙</th>
                    <th>메모</th>
                    <th>활성</th>
                    <th>작업</th>
                </tr>
                </thead>
                <tbody>
                {#if items.length === 0}
                    <tr><td colspan="9" class="has-text-grey">데이터 없음</td></tr>
                {:else}
                    {#each items as it, i}
                        <tr>
                            <td class="has-text-grey-light">{(page - 1) * perPage + i + 1}</td>
                            <th scope="row"># {it.id}</th>
                            <td>{it.name}</td>
                            <td><span class="tag is-light">{it.code}</span></td>
                            <td>{it.max_merge_qty}</td>
                            <td>{it.spec || '-'}</td>
                            <td><NoteBadge note={it.description} title="설명" /></td>
                            <td class="actions">
                                <label class="checkbox" aria-label="활성 토글">
                                    <input type="checkbox" checked={!!it.is_active}
                                           on:change={(e) => toggleActive(it, e.currentTarget.checked)} />
                                    <span class="ml-1">{it.is_active ? '활성' : '비활성'}</span>
                                </label>
                            </td>
                            <td class="actions">
                                <div class="buttons">
                                    <button class="button is-warning is-light is-small" type="button" on:click={() => copyProduct(it)} title="복사" aria-label="복사">
                                        <span class="material-icons" aria-hidden="true">content_copy</span>
                                    </button>
                                    <button class="button is-info is-small" type="button" on:click={() => openEdit(it)} title="수정" aria-label="수정">
                                        <span class="material-icons" aria-hidden="true">edit</span>
                                    </button>
                                    <a class="button is-link is-light is-small" href={`/products/${it.id}/product-name-mappings`} title="채널별 이름 매핑" aria-label="채널별 이름 매핑">
                                        <span class="material-icons" aria-hidden="true">rule_folder</span>
                                    </a>
                                    <button class="button is-danger is-small" type="button" on:click={() => askRemove(it)} title="삭제" aria-label="삭제">
                                        <span class="material-icons" aria-hidden="true">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    {/each}
                {/if}
                </tbody>
            </table>
        </div>

        <nav class="pagination is-centered mt-4" role="navigation" aria-label="pagination">
            <button class="pagination-previous button" on:click={prevPage} disabled={page <= 1}>이전</button>
            <button class="pagination-next button" on:click={nextPage} disabled={page >= lastPage}>다음</button>
            <ul class="pagination-list">
                <li><span class="pagination-link is-current" aria-current="page">{page} / {lastPage || 1}</span></li>
                <li><span class="pagination-link is-disabled">({from || 0}-{to || 0} / {total})</span></li>
            </ul>
        </nav>
    </div>
</section>

<Modal open={modalOpen} title={editing ? '상품 수정' : '상품 등록'}
       ariaDescription="상품 기본 정보를 입력하고 저장하세요."
       width={640} maxHeight={700} draggable on:close={closeModal}>
    <svelte:fragment slot="body">
        <form on:submit|preventDefault={save} autocomplete="off">
            <div class="field">
                <label class="label" for="p-name">상품명</label>
                <div class="control">
                    <input id="p-name" class="input" type="text" bind:value={form.name} required aria-required="true" bind:this={firstInput} />
                </div>
            </div>
            <div class="field">
                <label class="label" for="p-code">제품코드 (code)</label>
                <div class="control"><input id="p-code" class="input" type="text" bind:value={form.code} required aria-required="true" /></div>
                <p class="help">영문/숫자/하이픈 권장, 100자 이하</p>
            </div>
            <div class="field">
                <label class="label" for="p-maxmerge">최대 합포장 개수</label>
                <div class="control"><input id="p-maxmerge" class="input" type="number" min="1" step="1" bind:value={form.max_merge_qty} required /></div>
            </div>
            <div class="field">
                <label class="label" for="p-spec">스펙</label>
                <div class="control"><input id="p-spec" class="input" type="text" bind:value={form.spec} /></div>
            </div>
            <div class="field">
                <label class="label" for="p-desc">설명</label>
                <div class="control"><textarea id="p-desc" class="textarea" rows="3" bind:value={form.description}></textarea></div>
            </div>
            <div class="field">
                <label class="checkbox" for="p-active">
                    <input id="p-active" type="checkbox" bind:checked={form.is_active} />
                    <span class="ml-1">활성화</span>
                </label>
            </div>
        </form>
    </svelte:fragment>
    <svelte:fragment slot="footer">
        <div class="field is-grouped">
            <div class="control">
                <button class="button is-link" type="button" on:click={save}>
                    <span class="material-icons" aria-hidden="true">save</span>&nbsp;저장
                </button>
            </div>
            <div class="control"><button class="button" type="button" on:click={closeModal}>취소</button></div>
        </div>
    </svelte:fragment>
</Modal>

<ConfirmModal
        open={confirmOpen}
        title="상품 삭제"
        message={`상품 <strong>${confirmTarget?.name ?? ''}</strong> 을(를) 삭제하시겠습니까?`}
        confirmText="영구 삭제" cancelText="취소" confirmClass="is-danger"
        busy={confirmBusy} draggable
        on:confirm={async () => { confirmBusy = true; await reallyRemove(confirmTarget); confirmBusy = false; confirmOpen = false; confirmTarget = null; }}
        on:cancel={() => { confirmOpen = false; confirmTarget = null; }}
/>

<style>
    .products-table td, .products-table th { padding: .4rem .6rem; }
    .pagination .pagination-link.is-disabled { opacity:.6; pointer-events:none; }
</style>
