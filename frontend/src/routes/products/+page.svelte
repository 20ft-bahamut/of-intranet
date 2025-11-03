<script>
    import { fetchJson, qs } from '$lib/api/client.js';
    import ConfirmModal from '$lib/components/ConfirmModal.svelte';
    import NoteBadge from '$lib/components/NoteBadge.svelte';
    import Modal from '$lib/components/Modal.svelte';
    import SearchBar from '$lib/components/SearchBar.svelte';
    import { toast } from '$lib/stores/toast.js';

    // ===== 상태 =====
    let items = [];
    let loading = false;
    let error = null;

    // 검색 상태
    let q = '';
    let onlyActive = ''; // '' | '1' | '0'

    // 삭제 확인
    let confirmOpen = false;
    let confirmTarget = null;
    let confirmBusy = false;

    // 등록/수정 모달
    let modalOpen = false;
    let editing = null;
    let firstInput;

    function emptyForm() {
        return {
            name: '',
            code: '',
            max_merge_qty: 1,
            spec: '',
            description: '',
            is_active: true
        };
    }
    let form = emptyForm();

    // ===== 데이터 =====
    async function load() {
        loading = true;
        error = null;
        try {
            const res = await fetchJson(
                '/products' +
                qs({ q: q || undefined, is_active: onlyActive === '' ? undefined : onlyActive })
            );
            if (!res.ok) throw new Error(res.error || '불러오기 실패');
            items = Array.isArray(res.data) ? res.data : [];
            toast.info(`상품 ${items.length}개 불러옴`);
        } catch (e) {
            error = e.message || String(e);
        } finally {
            loading = false;
        }
    }
    load();

    // ===== 모달 =====
    function openNew() {
        editing = null;
        form = emptyForm();
        modalOpen = true;
        queueMicrotask(() => firstInput?.focus());
    }
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
        queueMicrotask(() => firstInput?.focus());
    }
    function closeModal() {
        modalOpen = false;
        editing = null;
    }

    // ===== 저장 =====
    async function save() {
        const payload = { ...form, max_merge_qty: parseInt(form.max_merge_qty || 1, 10) };
        try {
            const url = editing ? `/products/${editing.id}` : '/products';
            const method = editing ? 'PUT' : 'POST';
            const res = await fetchJson(url, { method, body: JSON.stringify(payload) });

            if (!res.ok) {
                const first = res.errors && Object.values(res.errors)[0]?.[0];
                toast.danger(first || res.error || '저장 실패');
                return;
            }
            toast.success(editing ? '상품을 수정했습니다.' : '상품을 등록했습니다.');
            closeModal();
            await load();
        } catch (e) {
            toast.danger('저장 실패: ' + (e.message || String(e)));
        }
    }

    // ===== 삭제 =====
    function askRemove(row) {
        confirmTarget = row;
        confirmOpen = true;
    }
    async function reallyRemove(row) {
        try {
            const res = await fetchJson(`/products/${row.id}`, { method: 'DELETE' });
            if (!res.ok) throw new Error(res.error || '삭제 실패');
            toast.success('삭제 완료');
            await load();
        } catch (e) {
            toast.danger('삭제 실패: ' + (e.message || String(e)));
        }
    }

    // ===== 활성 토글 =====
    async function toggleActive(row, checked) {
        try {
            const res = await fetchJson(`/products/${row.id}`, {
                method: 'PUT',
                body: JSON.stringify({
                    name: row.name,
                    code: row.code,
                    max_merge_qty: row.max_merge_qty,
                    spec: row.spec,
                    description: row.description,
                    is_active: !!checked
                })
            });
            if (!res.ok) throw new Error(res.error || '상태 변경 실패');
            toast.success(`활성 상태를 ${checked ? '활성' : '비활성'}으로 변경했습니다.`);
            await load();
        } catch (e) {
            toast.danger('상태 변경 실패: ' + (e.message || String(e)));
        }
    }

    // 검색바 이벤트 핸들러
    function doSearch() { load(); }
    function doReset() { q = ''; onlyActive = ''; load(); }
</script>

<svelte:head>
    <title>상품 관리 · OF Intranet</title>
    <meta name="description" content="상품을 등록·수정·삭제하고 활성 상태를 관리합니다." />
</svelte:head>

<section class="section" aria-labelledby="pageTitle">
    <div class="container">
        <header class="mb-4">
            <h1 id="pageTitle" class="title is-4">
                <span class="material-icons" aria-hidden="true">inventory_2</span>&nbsp;상품 관리
            </h1>
            <p class="subtitle is-6">상품명/코드/스펙/합포장/활성 등을 관리합니다.</p>
        </header>

        {#if error}
            <article class="message is-danger" aria-live="polite">
                <div class="message-body"><strong>오류:</strong> {error}</div>
            </article>
        {/if}

        <!-- 도구줄 -->
        <div class="level mb-3" aria-label="도구 모음">
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
                <span class="tag is-light" aria-live="polite">총 {items.length}개</span>
                {#if loading}<span class="tag is-info">불러오는 중…</span>{/if}
            </div>
        </div>

        <!-- 목록 -->
        <div class="table-container" role="region" aria-label="상품 목록 테이블">
            <table class="table is-fullwidth is-striped is-hoverable products-table">
                <caption class="is-sr-only">
                    등록된 상품 목록 표. 열: ID, 상품명, 코드, 최대 합포장, 스펙, 메모, 활성, 작업
                </caption>
                <thead>
                <tr>
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
                    <tr><td colspan="8" class="has-text-grey">데이터 없음</td></tr>
                {:else}
                    {#each items as it}
                        <tr>
                            <th scope="row">{it.id}</th>
                            <td>{it.name}</td>
                            <td><span class="tag is-light">{it.code}</span></td>
                            <td>{it.max_merge_qty}</td>
                            <td>{it.spec || '-'}</td>
                            <td><NoteBadge note={it.description} title="설명" /></td>
                            <td class="actions">
                                <label class="checkbox" aria-label="활성 토글">
                                    <input
                                            type="checkbox"
                                            checked={!!it.is_active}
                                            on:change={(e) => toggleActive(it, e.currentTarget.checked)}
                                    />
                                    <span class="ml-1">{it.is_active ? '활성' : '비활성'}</span>
                                </label>
                            </td>
                            <td class="actions">
                                <div class="buttons">
                                    <button class="button is-danger is-small" type="button" on:click={() => askRemove(it)} title="삭제" aria-label="삭제">
                                        <span class="material-icons" aria-hidden="true">delete</span>
                                    </button>
                                    <button class="button is-info is-small" type="button" on:click={() => openEdit(it)} title="수정" aria-label="수정">
                                        <span class="material-icons" aria-hidden="true">edit</span>
                                    </button>
                                    <a class="button is-link is-light is-small" href={`/products/${it.id}/product-name-mappings`} title="채널별 이름 매핑" aria-label="채널별 이름 매핑">
                                        <span class="material-icons" aria-hidden="true">rule_folder</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    {/each}
                {/if}
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- 등록/수정 모달 (공통 Modal 사용 / 드래그 가능) -->
<Modal
        open={modalOpen}
        title={editing ? '상품 수정' : '상품 등록'}
        ariaDescription="상품 기본 정보를 입력하고 저장하세요."
        width={640}
        maxHeight={700}
        draggable={true}
        on:close={closeModal}
>
    <svelte:fragment slot="body">
        <form on:submit|preventDefault={save} autocomplete="off">
            <div class="field">
                <label class="label" for="p-name">상품명</label>
                <div class="control">
                    <input id="p-name" class="input" type="text" bind:value={form.name} required aria-required="true" bind:this={firstInput} />
                </div>
                <p class="help">150자 이하</p>
            </div>

            <div class="field">
                <label class="label" for="p-code">제품코드 (code)</label>
                <div class="control">
                    <input id="p-code" class="input" type="text" bind:value={form.code} required aria-required="true" />
                </div>
                <p class="help">식별용 코드 (SKU와 별개). 영문/숫자/하이픈 권장, 100자 이하</p>
            </div>

            <div class="field">
                <label class="label" for="p-maxmerge">최대 합포장 개수</label>
                <div class="control">
                    <input id="p-maxmerge" class="input" type="number" min="1" step="1" bind:value={form.max_merge_qty} required />
                </div>
                <p class="help">정수(최소 1)</p>
            </div>

            <div class="field">
                <label class="label" for="p-spec">스펙</label>
                <div class="control">
                    <input id="p-spec" class="input" type="text" bind:value={form.spec} />
                </div>
                <p class="help">예: 5kg 박스, 10kg 박스 (100자 이하)</p>
            </div>

            <div class="field">
                <label class="label" for="p-desc">설명</label>
                <div class="control">
                    <textarea id="p-desc" class="textarea" rows="4" bind:value={form.description}></textarea>
                </div>
                <p class="help">제품 관리자용 설명(간략)</p>
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
            <div class="control">
                <button class="button" type="button" on:click={closeModal}>취소</button>
            </div>
        </div>
    </svelte:fragment>
</Modal>

<!-- 삭제 확인 모달 (드래그 지원) -->
<ConfirmModal
        open={confirmOpen}
        title="상품 삭제"
        message={`상품 <strong>${confirmTarget?.name ?? ''}</strong> 을(를) 삭제하시겠습니까?`}
        confirmText="영구 삭제"
        cancelText="취소"
        confirmClass="is-danger"
        busy={confirmBusy}
        draggable={true}
        on:confirm={async () => {
    confirmBusy = true;
    await reallyRemove(confirmTarget);
    confirmBusy = false;
    confirmOpen = false;
    confirmTarget = null;
  }}
        on:cancel={() => { confirmOpen = false; confirmTarget = null; }}
/>

<style>
    /* 페이지 전용: 테이블 셀 패딩만 약간 컴팩트 */
    .products-table td, .products-table th { padding: .4rem .6rem; }
</style>
