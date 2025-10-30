<script>
    import { page } from '$app/stores';
    import { fetchJson } from '$lib/api/client.js';
    import ConfirmModal from '$lib/components/ConfirmModal.svelte';

    // 라우트 파라미터
    $: channelId = $page.params.id;

    // 상태
    let channel = null;
    let items = [];
    let loading = false;
    let error = null;
    let flash = null; // {type,text}

    // 모달/폼
    let modalOpen = false;
    let editing = null; // row or null
    let form = { cell_ref: '', expected_label: '', is_required: true };
    let firstInput;

    // 삭제 확인 모달
    let confirmOpen = false;
    let confirmTarget = null;
    let confirmBusy = false;

    // 유틸
    function setFlash(type, text, ms = 2600) {
        flash = { type, text };
        setTimeout(() => (flash = null), ms);
    }
    const CELL_RX = /^[A-Z]{1,3}[1-9]\d{0,4}$/; // A1, B3 등

    function validateForm() {
        const ref = (form.cell_ref || '').trim().toUpperCase();
        if (!CELL_RX.test(ref)) return '셀 위치는 A1, B3 형식으로 입력하세요.';
        if (!String(form.expected_label || '').trim()) return '기대 라벨을 입력하세요.';
        return null;
    }

    // API
    async function fetchChannel() {
        const res = await fetchJson(`/channels/${channelId}`);
        if (!res.ok) throw new Error(res.error || '채널 정보 조회 실패');
        channel = res.data;
    }
    async function fetchRules() {
        const res = await fetchJson(`/channels/${channelId}/excel-validations`);
        if (!res.ok) throw new Error(res.error || '규칙 목록 조회 실패');
        items = Array.isArray(res.data) ? res.data : [];
    }
    async function fetchAll() {
        loading = true; error = null;
        try {
            await Promise.all([fetchChannel(), fetchRules()]);
            setFlash('info', `규칙 ${items.length}개 불러옴`);
        } catch (e) {
            error = e.message || String(e);
        } finally {
            loading = false;
        }
    }

    // 모달
    function openNew() {
        editing = null;
        form = { cell_ref: '', expected_label: '', is_required: true };
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
    function onModalKeydown(e) { if (e.key === 'Escape') { e.stopPropagation(); closeModal(); } }

    // 저장/삭제
    async function save() {
        const err = validateForm();
        if (err) return setFlash('warning', err, 3200);

        const payload = {
            cell_ref: form.cell_ref.trim().toUpperCase(),
            expected_label: String(form.expected_label).trim(),
            is_required: !!form.is_required
        };

        try {
            const url = editing
                ? `/channels/${channelId}/excel-validations/${editing.id}`
                : `/channels/${channelId}/excel-validations`;
            const method = editing ? 'PUT' : 'POST';
            const res = await fetchJson(url, { method, body: JSON.stringify(payload) });
            if (!res.ok) throw new Error(res.error || '저장 실패');

            setFlash('success', editing ? '규칙을 수정했습니다.' : '규칙을 등록했습니다.');
            closeModal();
            await fetchRules();
        } catch (e) {
            setFlash('danger', '저장 실패: ' + (e.message || String(e)));
        }
    }
    function askRemove(row) { confirmTarget = row; confirmOpen = true; }
    async function reallyRemove(row) {
        try {
            const res = await fetchJson(`/channels/${channelId}/excel-validations/${row.id}`, { method: 'DELETE' });
            if (!res.ok) throw new Error(res.error || '삭제 실패');
            setFlash('success', '삭제 완료');
            await fetchRules();
        } catch (e) {
            setFlash('danger', '삭제 실패: ' + (e.message || String(e)));
        }
    }

    // 초기 로드
    $: fetchAll();
</script>

<svelte:head>
    <title>엑셀 검증 규칙 · 채널 #{channelId} · OF Intranet</title>
    <meta name="description" content="채널별 엑셀 헤더/셀 위치의 기대 라벨과 필수 여부를 관리합니다." />
</svelte:head>

<section class="section" aria-labelledby="pageTitle">
    <div class="container">
        <nav class="breadcrumb" aria-label="breadcrumbs">
            <ul>
                <li><a href="/channels">채널 관리</a></li>
                <li class="is-active"><a aria-current="page">엑셀 검증 규칙</a></li>
            </ul>
        </nav>

        <header class="mb-4">
            <h1 id="pageTitle" class="title is-4">
                <span class="material-icons" aria-hidden="true">rule</span>&nbsp;엑셀 검증 규칙
            </h1>
            <p class="subtitle is-6">
                채널:
                {#if channel}
                    <strong>{channel.name}</strong>
                    <span class="tag is-light ml-2">{channel.code}</span>
                {:else}
                    <span class="has-text-grey">로딩 중…</span>
                {/if}
            </p>
        </header>

        {#if flash}
            <article class="message {flash.type === 'success' ? 'is-success' : flash.type === 'danger' ? 'is-danger' : flash.type === 'warning' ? 'is-warning' : 'is-info'}" aria-live="polite">
                <div class="message-body">{flash.text}</div>
            </article>
        {/if}
        {#if error}
            <article class="message is-danger" aria-live="polite">
                <div class="message-body"><strong>오류:</strong> {error}</div>
            </article>
        {/if}

        <div class="level mb-3" aria-label="도구 모음">
            <div class="level-left">
                <a class="button" href="/channels">
                    <span class="material-icons" aria-hidden="true">arrow_back</span>&nbsp;목록으로
                </a>
            </div>
            <div class="level-right">
                <button class="button is-link" type="button" on:click={openNew}>
                    <span class="material-icons" aria-hidden="true">add</span>&nbsp;규칙 추가
                </button>
                {#if loading}<span class="tag is-info ml-2">불러오는 중…</span>{/if}
                <span class="tag is-light ml-2">총 {items.length}개</span>
            </div>
        </div>

        <section aria-labelledby="tableTitle">
            <h2 id="tableTitle" class="is-sr-only">엑셀 검증 규칙 목록</h2>
            <div class="table-container" role="region" aria-label="검증 규칙 테이블">
                <table class="table is-fullwidth is-striped is-hoverable rules-table">
                    <caption class="is-sr-only">열: 셀 위치, 기대 라벨, 필수여부, 작업</caption>
                    <thead>
                    <tr>
                        <th scope="col">셀 위치</th>
                        <th scope="col">기대 라벨</th>
                        <th scope="col">필수</th>
                        <th scope="col">작업</th>
                    </tr>
                    </thead>
                    <tbody>
                    {#if items.length === 0}
                        <tr><td colspan="4" class="has-text-grey">데이터 없음</td></tr>
                    {:else}
                        {#each items as it}
                            <tr>
                                <th scope="row"><code>{it.cell_ref}</code></th>
                                <td>{it.expected_label}</td>
                                <td>{it.is_required ? '✅' : '—'}</td>
                                <td class="actions">
                                    <div class="buttons">
                                        <button class="button is-info is-small" type="button" title="수정" aria-label="수정" on:click={() => openEdit(it)}>
                                            <span class="material-icons" aria-hidden="true">edit</span>
                                        </button>
                                        <button class="button is-danger is-small" type="button" title="삭제" aria-label="삭제" on:click={() => askRemove(it)}>
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
        </section>
    </div>
</section>

{#if modalOpen}
    <div class="modal is-active" role="dialog" aria-modal="true" aria-labelledby="modalTitle" aria-describedby="modalDesc" on:keydown={onModalKeydown}>
        <div class="modal-background" on:click={closeModal}></div>
        <div class="modal-card">
            <header class="modal-card-head">
                <p id="modalTitle" class="modal-card-title">{editing ? '규칙 수정' : '규칙 추가'}</p>
                <button class="delete" type="button" aria-label="닫기" on:click={closeModal}></button>
            </header>

            <section class="modal-card-body">
                <p id="modalDesc" class="is-sr-only">엑셀 셀 위치와 기대 라벨, 필수 여부를 입력하세요.</p>

                <form on:submit|preventDefault={save} autocomplete="off">
                    <!-- 셀 위치 -->
                    <div class="field">
                        <label class="label" for="f-ref">셀 위치</label>
                        <div class="control">
                            <input id="f-ref" class="input" type="text" placeholder="예: A1"
                                   bind:value={form.cell_ref}
                                   required aria-required="true" bind:this={firstInput} />
                        </div>
                        <p class="help">A1, B3 같은 표기만 허용됩니다.</p>
                    </div>

                    <!-- 기대 라벨 -->
                    <div class="field">
                        <label class="label" for="f-label">기대 라벨</label>
                        <div class="control">
                            <input id="f-label" class="input" type="text" placeholder="예: 주문번호"
                                   bind:value={form.expected_label}
                                   required aria-required="true" />
                        </div>
                    </div>

                    <!-- 필수 여부 -->
                    <div class="field">
                        <label class="checkbox" for="f-required">
                            <input id="f-required" type="checkbox" bind:checked={form.is_required} />
                            필수 항목
                        </label>
                    </div>

                    <!-- 액션 -->
                    <div class="field is-grouped">
                        <div class="control">
                            <button class="button is-link" type="submit">
                                <span class="material-icons" aria-hidden="true">save</span>&nbsp;저장
                            </button>
                        </div>
                        <div class="control">
                            <button class="button" type="button" on:click={closeModal}>취소</button>
                        </div>
                    </div>
                </form>
            </section>

            <footer class="modal-card-foot">
                <p class="is-size-7 has-text-grey">필수 체크 해제 시 존재 여부만 확인합니다.</p>
            </footer>
        </div>
    </div>
{/if}

<!-- 삭제 확인 모달 -->
<ConfirmModal
        open={confirmOpen}
        title="규칙 삭제"
        message={`셀 <code>${confirmTarget?.cell_ref ?? ''}</code> 규칙을 삭제할까요?`}
        confirmText="삭제"
        cancelText="취소"
        confirmClass="is-danger"
        busy={confirmBusy}
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
    .rules-table td, .rules-table th { padding: .45rem .6rem; }
    td.actions { white-space: nowrap; vertical-align: middle; width: 1%; }
    td.actions .buttons { display: inline-flex; flex-wrap: nowrap; gap: .25rem; margin: 0; }
    td.actions .button.is-small { padding: .25rem .45rem; line-height: 1.1; height: auto; }
    td.actions .material-icons { font-size: 18px; line-height: 1; }
</style>
