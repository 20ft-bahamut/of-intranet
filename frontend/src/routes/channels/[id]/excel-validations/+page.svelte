<script>
    import { onMount } from 'svelte';
    import { page } from '$app/stores';
    import { fetchJson, qs } from '$lib/api/client.js';
    import SearchBar from '$lib/components/SearchBar.svelte';
    import Modal from '$lib/components/Modal.svelte';
    import ConfirmModal from '$lib/components/ConfirmModal.svelte';
    import { toast } from '$lib/stores/toast.js';

    // ===== 파라미터 =====
    let channelId;
    $: channelId = Number(($page?.params?.id) ?? 0);

    // ===== 상단 표시용 채널 =====
    let channel = null; // {id,name,code,...}

    // ===== 목록/검색 =====
    let items = [];     // 검증 규칙 목록
    let loading = false;
    let error = null;

    let q = '';         // 검색어(셀/라벨)
    // 서버가 q 미지원해도 안전: 서버 전체 호출 후 프론트 필터

    // ===== 등록/수정 모달 =====
    let modalOpen = false;
    let editing = null;
    let firstInput;

    function emptyForm() {
        return {
            cell_ref: '',
            expected_label: '',
            is_required: true
        };
    }
    let form = emptyForm();
    const cellRefRe = /^[A-Z]{1,3}[1-9]\d{0,4}$/;

    // ===== 삭제 확인 =====
    let confirmOpen = false;
    let confirmTarget = null;
    let confirmBusy = false;

    // ===== 초기 로드 =====
    onMount(async () => {
        await loadChannel();
        await load();
    });

    async function loadChannel() {
        const res = await fetchJson(`/channels/${channelId}`);
        if (res.ok) channel = res.data;
    }

    async function load() {
        if (!channelId) return;
        loading = true; error = null;
        try {
            // 백엔드가 q 미지원해도 일단 전체 받음
            const res = await fetchJson(`/channels/${channelId}/excel-validations` + qs({ q: q || undefined }));
            if (!res.ok) throw new Error(res.error || '불러오기 실패');
            let list = Array.isArray(res.data) ? res.data : [];

            // 프론트 필터 (서버 q 미지원 대비)
            const s = (q || '').trim().toLowerCase();
            if (s) {
                list = list.filter(it =>
                    (it.cell_ref || '').toLowerCase().includes(s) ||
                    (it.expected_label || '').toLowerCase().includes(s)
                );
            }

            // 정렬: 셀 기준(A1 → A2 → B1 …) 간단히 알파/숫자 분리 정렬
            items = list.sort((a,b) => {
                const [ac, ar] = String(a.cell_ref || '').match(/^([A-Z]{1,3})(\d{1,5})$/)?.slice(1) ?? ['Z', '99999'];
                const [bc, br] = String(b.cell_ref || '').match(/^([A-Z]{1,3})(\d{1,5})$/)?.slice(1) ?? ['Z', '99999'];
                if (ac === bc) return Number(ar) - Number(br);
                return ac.localeCompare(bc);
            });

            toast.info(`검증 규칙 ${items.length}개 불러옴`);
        } catch (e) {
            error = e.message || String(e);
        } finally {
            loading = false;
        }
    }

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
            cell_ref: row.cell_ref ?? '',
            expected_label: row.expected_label ?? '',
            is_required: !!row.is_required
        };
        modalOpen = true;
        queueMicrotask(() => firstInput?.focus());
    }
    function closeModal() { modalOpen = false; editing = null; }

    // ===== 저장 =====
    async function save() {
        // 간단 클라 검증
        const cell = (form.cell_ref || '').trim().toUpperCase();
        if (!cellRefRe.test(cell)) {
            toast.danger('셀 참조 형식이 올바르지 않습니다. 예: A1, B3, AA10');
            return;
        }
        const payload = {
            cell_ref: cell,
            expected_label: (form.expected_label || '').trim(),
            is_required: !!form.is_required
        };

        try {
            const url = editing
                ? `/channels/${channelId}/excel-validations/${editing.id}`
                : `/channels/${channelId}/excel-validations`;
            const method = editing ? 'PUT' : 'POST';
            const res = await fetchJson(url, { method, body: JSON.stringify(payload) });

            if (!res.ok) {
                const first = res.errors && Object.values(res.errors)[0]?.[0];
                toast.danger(first || res.error || '저장 실패');
                return;
            }
            toast.success(editing ? '규칙을 수정했습니다.' : '규칙을 등록했습니다.');
            closeModal();
            await load();
        } catch (e) {
            toast.danger('저장 실패: ' + (e.message || String(e)));
        }
    }

    // ===== 삭제 =====
    function askRemove(row) { confirmTarget = row; confirmOpen = true; }
    async function reallyRemove(row) {
        try {
            const res = await fetchJson(`/channels/${channelId}/excel-validations/${row.id}`, { method: 'DELETE' });
            if (!res.ok) throw new Error(res.error || '삭제 실패');
            toast.success('삭제 완료');
            await load();
        } catch (e) {
            toast.danger('삭제 실패: ' + (e.message || String(e)));
        }
    }

    // 검색바 이벤트
    function doSearch(){ load(); }
    function doReset(){ q=''; load(); }
</script>

<svelte:head>
    <title>엑셀 검증 규칙 · OF Intranet</title>
    <meta name="description" content="채널 엑셀의 헤더/라벨 검증 규칙을 관리합니다." />
</svelte:head>

<section class="section" aria-labelledby="pageTitle">
    <div class="container">
        <header class="mb-4">
            <h1 id="pageTitle" class="title is-4">
                <span class="material-icons" aria-hidden="true">rule</span>&nbsp;엑셀 검증 규칙
            </h1>
            {#if channel}
                <p class="subtitle is-6">
                    대상 채널: <strong>{channel.name}</strong>
                    <span class="tag is-light ml-2">{channel.code}</span>
                </p>
            {/if}
        </header>

        {#if error}
            <article class="message is-danger" aria-live="polite">
                <div class="message-body"><strong>오류:</strong> {error}</div>
            </article>
        {/if}

        <!-- 도구줄 -->
        <div class="level mb-3" aria-label="도구 모음">
            <div class="level-left">
                <a class="button" href="/channels">
                    <span class="material-icons">arrow_back</span>&nbsp;채널 목록
                </a>
                <button class="button is-link ml-2" type="button" on:click={openNew}>
                    <span class="material-icons" aria-hidden="true">add_circle</span>&nbsp;규칙 등록
                </button>
            </div>

            <div class="level-right" style="gap:.5rem; align-items:center;">
                <SearchBar
                        bind:q
                        placeholder="셀 참조 또는 라벨로 검색 (예: A1, 주문번호)"
                        on:search={doSearch}
                        on:reset={doReset}
                />
                <span class="tag is-light" aria-live="polite">총 {items.length}개</span>
                {#if loading}<span class="tag is-info">불러오는 중…</span>{/if}
            </div>
        </div>

        <!-- 목록 -->
        <div class="table-container" role="region" aria-label="검증 규칙 목록 테이블">
            <table class="table is-fullwidth is-striped is-hoverable validations-table">
                <caption class="is-sr-only">
                    엑셀 검증 규칙 표. 열: ID, 셀, 예상 라벨, 필수여부, 작업
                </caption>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>셀</th>
                    <th>예상 라벨</th>
                    <th>필수</th>
                    <th>작업</th>
                </tr>
                </thead>
                <tbody>
                {#if items.length === 0}
                    <tr><td colspan="5" class="has-text-grey">데이터 없음</td></tr>
                {:else}
                    {#each items as it}
                        <tr>
                            <th scope="row">{it.id}</th>
                            <td><span class="tag is-light">{it.cell_ref}</span></td>
                            <td>{it.expected_label}</td>
                            <td>{it.is_required ? '예' : '아니오'}</td>
                            <td class="actions">
                                <div class="buttons">
                                    <button class="button is-danger is-small" type="button" on:click={() => askRemove(it)} title="삭제">
                                        <span class="material-icons">delete</span>
                                    </button>
                                    <button class="button is-info is-small" type="button" on:click={() => openEdit(it)} title="수정">
                                        <span class="material-icons">edit</span>
                                    </button>
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

<!-- 등록/수정 모달 (공통 Modal 사용) -->
<Modal
        open={modalOpen}
        title={editing ? '규칙 수정' : '규칙 등록'}
        ariaDescription="셀 참조와 라벨을 입력하세요."
        width={560}
        maxHeight={520}
        draggable={true}
        on:close={closeModal}
>
    <svelte:fragment slot="body">
        <form on:submit|preventDefault={save} autocomplete="off">
            <div class="columns is-variable is-2">
                <div class="column is-4">
                    <div class="field">
                        <label class="label" for="v-cell">셀 참조</label>
                        <div class="control">
                            <input id="v-cell" class="input" type="text" bind:value={form.cell_ref}
                                   required aria-required="true" maxlength="6" placeholder="예: A1"
                                   bind:this={firstInput} on:blur={() => form.cell_ref = (form.cell_ref||'').toUpperCase()} />
                        </div>
                        <p class="help">형식: A1, B3, AA10 (정규식: ^[A-Z]{1,3}[1-9]\d{0,4}$)</p>
                    </div>
                </div>
                <div class="column">
                    <div class="field">
                        <label class="label" for="v-label">예상 라벨</label>
                        <div class="control">
                            <input id="v-label" class="input" type="text" bind:value={form.expected_label}
                                   required aria-required="true" placeholder="예: 주문번호" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="field">
                <label class="checkbox" for="v-required">
                    <input id="v-required" type="checkbox" bind:checked={form.is_required} />
                    <span class="ml-1">필수 라벨</span>
                </label>
            </div>
        </form>
    </svelte:fragment>

    <svelte:fragment slot="footer">
        <div class="field is-grouped">
            <div class="control">
                <button class="button is-link" type="button" on:click={save}>
                    <span class="material-icons">save</span>&nbsp;저장
                </button>
            </div>
            <div class="control">
                <button class="button" type="button" on:click={closeModal}>취소</button>
            </div>
        </div>
    </svelte:fragment>
</Modal>

<!-- 삭제 확인 (드래그 가능) -->
<ConfirmModal
        open={confirmOpen}
        title="규칙 삭제"
        message={`규칙 <strong>#${confirmTarget?.id ?? ''}</strong> 을(를) 삭제하시겠습니까?`}
        confirmText="영구 삭제"
        cancelText="취소"
        confirmClass="is-danger"
        busy={confirmBusy}
        draggable={true}
        on:confirm={async () => { confirmBusy = true; await reallyRemove(confirmTarget); confirmBusy = false; confirmOpen = false; confirmTarget = null; }}
        on:cancel={() => { confirmOpen = false; confirmTarget = null; }}
/>

<style>
    /* 이 페이지 전용: 표 패딩만 살짝 컴팩트 */
    .validations-table td, .validations-table th { padding: .4rem .6rem; }
</style>