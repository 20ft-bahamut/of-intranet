<script>
    import { fetchJson } from '$lib/api/client.js';
    import ConfirmModal from '$lib/components/ConfirmModal.svelte';
    import { onMount } from 'svelte';

    // ===== 상태 =====
    let items = [];
    let loading = false;
    let error = null;
    let flash = null; // {type,text}
    let modalOpen = false;
    let editing = null;

    // 검색 상태
    let q = '';

    // 폼 상태 (제품 스키마 반영)
    let form = {
        name: '',           // varchar(150)
        code: '',           // varchar(100) - 제품코드 (SKU 아님)
        max_merge_qty: 1,   // int
        spec: '',           // varchar(100)
        description: '',    // text
        is_active: true
    };

    let firstInput;
    let confirmOpen = false;
    let confirmTarget = null;
    let confirmBusy = false;

    function setFlash(type, text, ms = 2500) {
        flash = { type, text };
        setTimeout(() => (flash = null), ms);
    }

    const API = {
        index: (q) => (q ? `/products?q=${encodeURIComponent(q)}` : '/products'),
        show: (id) => `/products/${id}`,
        create: () => '/products',
        update: (id) => `/products/${id}`,
        destroy: (id) => `/products/${id}`
    };

    async function fetchProducts() {
        loading = true; error = null;
        try {
            const res = await fetchJson(API.index(q));
            if (!res.ok) throw new Error(res.error || '불러오기 실패');
            items = Array.isArray(res.data) ? res.data : (res.data?.data ?? []);
            setFlash('info', `상품 ${items.length}개 불러옴`);
        } catch (e) {
            error = e.message || String(e);
            items = [];
        } finally { loading = false; }
    }

    $: filtered = items.filter((it) => {
        if (!q) return true;
        const s = q.toLowerCase().trim();
        return (it.name || '').toLowerCase().includes(s) || (it.code || '').toLowerCase().includes(s);
    });

    function openNew() {
        editing = null;
        form = { name: '', code: '', max_merge_qty: 1, spec: '', description: '', is_active: true };
        modalOpen = true;
        queueMicrotask(() => firstInput?.focus());
    }

    function openEdit(item) {
        editing = item;
        form = {
            name: item.name ?? '',
            code: item.code ?? '',
            max_merge_qty: item.max_merge_qty ?? 1,
            spec: item.spec ?? '',
            description: item.description ?? '',
            is_active: !!item.is_active
        };
        modalOpen = true;
        queueMicrotask(() => firstInput?.focus());
    }

    function closeModal() { modalOpen = false; editing = null; }
    function onModalKeydown(e) { if (e.key === 'Escape') { e.stopPropagation(); closeModal(); } }

    // 클라이언트 유효성(간단)
    function validatePayload(p) {
        const errs = {};
        if (!p.name || !p.name.trim()) errs.name = '상품명을 입력하세요.';
        if (!p.code || !p.code.trim()) errs.code = '제품코드를 입력하세요.';
        if (String(p.name).length > 150) errs.name = '상품명은 150자 이하입니다.';
        if (String(p.code).length > 100) errs.code = '제품코드는 100자 이하입니다.';
        if (!Number.isInteger(p.max_merge_qty) || p.max_merge_qty < 1) errs.max_merge_qty = '최소 1 이상의 정수여야 합니다.';
        if (String(p.spec).length > 100) errs.spec = '스펙은 100자 이하입니다.';
        return errs;
    }

    async function save() {
        const payload = {
            name: String(form.name || '').trim(),
            code: String(form.code || '').trim(),
            max_merge_qty: parseInt(form.max_merge_qty || 1, 10) || 1,
            spec: String(form.spec || '').trim(),
            description: String(form.description || '').trim(),
            is_active: !!form.is_active
        };

        const v = validatePayload(payload);
        if (Object.keys(v).length) {
            setFlash('warning', Object.values(v).join(' '));
            return;
        }

        try {
            const url = editing ? API.update(editing.id) : API.create();
            const method = editing ? 'PUT' : 'POST';
            const res = await fetchJson(url, { method, body: JSON.stringify(payload) });
            if (!res.ok) throw new Error(res.error || '저장 실패');
            setFlash('success', editing ? '상품 수정 완료' : '상품 등록 완료');
            closeModal();
            await fetchProducts();
        } catch (e) {
            setFlash('danger', '저장 실패: ' + (e.message || String(e)));
        }
    }

    function askRemove(item) { confirmTarget = item; confirmOpen = true; }
    async function reallyRemove(item) {
        try {
            const res = await fetchJson(API.destroy(item.id), { method: 'DELETE' });
            if (!res.ok) throw new Error(res.error || '삭제 실패');
            setFlash('success', '삭제 완료');
            await fetchProducts();
        } catch (e) { setFlash('danger', '삭제 실패: ' + (e.message || String(e))); }
    }

    async function toggleActive(item, checked) {
        try {
            const res = await fetchJson(API.update(item.id), { method: 'PUT', body: JSON.stringify({ ...item, is_active: !!checked }) });
            if (!res.ok) throw new Error(res.error || '상태 변경 실패');
            setFlash('success', `활성 상태 변경: ${checked ? '활성' : '비활성'}`);
            await fetchProducts();
        } catch (e) { setFlash('danger', '상태 변경 실패: ' + (e.message || String(e))); }
    }

    onMount(fetchProducts);
</script>

<svelte:head>
    <title>상품 관리 · OF Intranet</title>
    <meta name="description" content="제품 코드, 합포장 수량, 스펙, 설명 등을 포함한 상품 관리를 합니다." />
</svelte:head>

<section class="section" aria-labelledby="pageTitle">
    <div class="container">
        <header class="mb-4">
            <h1 id="pageTitle" class="title is-4">
                <span class="material-icons" aria-hidden="true">inventory_2</span>&nbsp;상품 관리
            </h1>
            <p class="subtitle is-6">제품 코드(code)와 상세 속성을 포함한 상품을 관리합니다.</p>
        </header>

        {#if flash}
            <article class="message {flash.type === 'success' ? 'is-success' : flash.type === 'danger' ? 'is-danger' : flash.type === 'warning' ? 'is-warning' : 'is-info'}" aria-live="polite">
                <div class="message-body">{flash.text}</div>
            </article>
        {/if}
        {#if error}
            <article class="message is-danger" aria-live="polite"><div class="message-body"><strong>오류:</strong> {error}</div></article>
        {/if}

        <div class="level mb-3" aria-label="도구 모음">
            <div class="level-left">
                <button class="button is-link" type="button" on:click={openNew}>
                    <span class="material-icons" aria-hidden="true">add_circle</span>&nbsp;새 상품 등록
                </button>
            </div>
            <div class="level-right">
                <form class="field has-addons" role="search" aria-label="상품 검색" on:submit|preventDefault={fetchProducts}>
                    <p class="control is-expanded">
                        <label for="q" class="is-hidden">검색어</label>
                        <input id="q" class="input" type="search" bind:value={q} placeholder="상품명 또는 제품코드로 검색" autocomplete="off" />
                    </p>
                    <p class="control">
                        <button class="button is-info" type="submit" aria-label="검색"><span class="material-icons" aria-hidden="true">search</span></button>
                    </p>
                    <p class="control">
                        <button class="button" type="button" on:click={() => { q=''; fetchProducts(); }} aria-label="초기화"><span class="material-icons" aria-hidden="true">restart_alt</span></button>
                    </p>
                </form>
                <span class="tag is-light ml-2" aria-live="polite">총 {filtered.length}개</span>
                {#if loading}<span class="tag is-info ml-2">불러오는 중…</span>{/if}
            </div>
        </div>

        <section aria-labelledby="tableTitle">
            <h2 id="tableTitle" class="is-sr-only">상품 목록</h2>
            <div class="table-container" role="region" aria-label="상품 목록 테이블">
                <table class="table is-fullwidth is-striped is-hoverable products-table">
                    <caption class="is-sr-only">등록된 상품의 표. 열: ID, 상품명, 제품코드, 합포장수, 스펙, 메모, 활성, 작업</caption>
                    <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">상품명</th>
                        <th scope="col">제품코드</th>
                        <th scope="col">합포장수</th>
                        <th scope="col">스펙</th>
                        <th scope="col"></th>
                        <th scope="col">활성</th>
                        <th scope="col">작업</th>
                    </tr>
                    </thead>
                    <tbody>
                    {#if filtered.length === 0}
                        <tr><td colspan="8" class="has-text-grey">데이터 없음</td></tr>
                    {:else}
                        {#each filtered as it}
                            <tr>
                                <th scope="row">{it.id}</th>
                                <td>{it.name}</td>
                                <td><span class="tag is-light">{it.code}</span></td>
                                <td>{it.max_merge_qty}</td>
                                <td>{it.spec || '-'}</td>
                                <td class="actions">
                                    {#if it.description}
                                        <span class="material-icons memo" aria-label="메모" data-tooltip={it.description}>sticky_note_2</span>
                                    {:else}
                                        <span class="material-icons has-text-grey-light" aria-hidden="true" title="메모 없음">sticky_note_2</span>
                                    {/if}
                                </td>
                                <td class="actions">
                                    <label class="checkbox" aria-label="활성 토글">
                                        <input type="checkbox" checked={!!it.is_active} on:change={(e)=>toggleActive(it,e.currentTarget.checked)} />
                                        <span class="ml-1">{it.is_active ? '활성' : '비활성'}</span>
                                    </label>
                                </td>
                                <td class="actions">
                                    <div class="buttons">
                                        <button class="button is-danger is-small" type="button" on:click={()=>askRemove(it)} title="삭제" aria-label="삭제">
                                            <span class="material-icons" aria-hidden="true">delete</span>
                                        </button>
                                        <button class="button is-info is-small" type="button" on:click={()=>openEdit(it)} title="수정" aria-label="수정">
                                            <span class="material-icons" aria-hidden="true">edit</span>
                                        </button>
                                        <a class="button is-link is-light is-small" href={`/products/${it.id}/product-name-mappings`} title="채널 명칭 매핑" aria-label="채널 명칭 매핑">
                                            <span class="material-icons" aria-hidden="true">link</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        {/each}
                    {/if}
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</section>

{#if modalOpen}
    <div class="modal is-active" role="dialog" aria-modal="true" aria-labelledby="modalTitle" aria-describedby="modalDesc" on:keydown={onModalKeydown}>
        <div class="modal-background" on:click={closeModal}></div>
        <div class="modal-card">
            <header class="modal-card-head">
                <p id="modalTitle" class="modal-card-title">{editing ? '상품 수정' : '상품 등록'}</p>
                <button class="delete" type="button" aria-label="닫기" on:click={closeModal}></button>
            </header>
            <section class="modal-card-body">
                <p id="modalDesc" class="is-sr-only">상품 기본 정보를 입력하고 저장하세요.</p>
                <form on:submit|preventDefault={save} autocomplete="off">
                    <div class="field">
                        <label class="label" for="p-name">상품명</label>
                        <div class="control"><input id="p-name" class="input" type="text" bind:value={form.name} required aria-required="true" bind:this={firstInput} /></div>
                        <p class="help">150자 이하</p>
                    </div>

                    <div class="field">
                        <label class="label" for="p-code">제품코드 (code)</label>
                        <div class="control"><input id="p-code" class="input" type="text" bind:value={form.code} required aria-required="true" /></div>
                        <p class="help">식별용 코드 (SKU와 별개). 영문/숫자/하이픈 권장, 100자 이하</p>
                    </div>

                    <div class="field">
                        <label class="label" for="p-maxmerge">최대 합포장 개수</label>
                        <div class="control"><input id="p-maxmerge" class="input" type="number" min="1" step="1" bind:value={form.max_merge_qty} required /></div>
                        <p class="help">정수(최소 1)</p>
                    </div>

                    <div class="field">
                        <label class="label" for="p-spec">스펙</label>
                        <div class="control"><input id="p-spec" class="input" type="text" bind:value={form.spec} /></div>
                        <p class="help">예: 5kg 박스, 10kg 박스 (100자 이하)</p>
                    </div>

                    <div class="field">
                        <label class="label" for="p-desc">설명</label>
                        <div class="control"><textarea id="p-desc" class="textarea" rows="4" bind:value={form.description}></textarea></div>
                        <p class="help">제품 관리자용 설명(간략)</p>
                    </div>

                    <div class="field">
                        <label class="checkbox" for="p-active"><input id="p-active" type="checkbox" bind:checked={form.is_active} /> 활성화</label>
                    </div>

                    <div class="field is-grouped">
                        <div class="control"><button class="button is-link" type="submit"><span class="material-icons" aria-hidden="true">save</span>&nbsp;저장</button></div>
                        <div class="control"><button class="button" type="button" on:click={closeModal}>취소</button></div>
                    </div>
                </form>
            </section>
            <footer class="modal-card-foot"><p class="is-size-7 has-text-grey">필수값 누락 시 저장되지 않습니다.</p></footer>
        </div>
    </div>
{/if}

<ConfirmModal open={confirmOpen} title="상품 삭제" message={`상품 <strong>${confirmTarget?.name ?? ''}</strong> 을(를) 삭제하시겠습니까?<br>연관 매핑도 함께 삭제될 수 있습니다.`} confirmText="영구 삭제" cancelText="취소" confirmClass="is-danger" busy={confirmBusy} on:confirm={async ()=>{ confirmBusy=true; await reallyRemove(confirmTarget); confirmBusy=false; confirmOpen=false; confirmTarget=null; }} on:cancel={()=>{ confirmOpen=false; confirmTarget=null; }} />

<style>
    .products-table td, .products-table th { padding: .4rem .6rem; }
    td.actions { white-space: nowrap; vertical-align: middle; width: 1%; }
    td.actions .buttons { display: inline-flex; flex-wrap: nowrap; gap: .25rem; margin: 0; }
    td.actions .button.is-small { padding: .25rem .45rem; line-height: 1.1; height: auto; }
    td.actions .material-icons { font-size: 18px; line-height: 1; }
    .is-sr-only { position:absolute !important; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); border:0; }
    /* 메모 툴팁 */
    .memo { position: relative; cursor: help; }
    .memo[data-tooltip]:hover::after {
        content: attr(data-tooltip);
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        top: 125%;              /* 위로 붙어서 잘리던 문제 → 아래로 표시 */
        min-width: 160px;
        max-width: 520px;
        background: rgba(54,54,54,.95);
        color: #fff;
        padding: .45rem .6rem;
        border-radius: .35rem;
        box-shadow: 0 6px 18px rgba(0,0,0,.25);
        white-space: pre-line;
        word-break: keep-all;
        text-align: left;
        font-size: .85rem;
        line-height: 1.35;
        z-index: 9999;          /* 상단 UI보다 위로 */
    }
    .memo[data-tooltip]:hover::before {
        content: '';
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        top: 115%;
        border: 6px solid transparent;
        border-bottom-color: rgba(54,54,54,.95); /* 아래쪽 툴팁 화살표 */
    }
    /* 테이블 컨테이너가 툴팁을 자르지 않도록 */
    .table-container { overflow: visible; }
</style>
