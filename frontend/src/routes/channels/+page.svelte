<script>
    import { fetchJson } from '$lib/api/client.js';
    import ConfirmModal from '$lib/components/ConfirmModal.svelte';

    // ===== 상태 =====
    let items = [];        // 원본 목록
    let loading = false;
    let error = null;
    let flash = null;      // {type,text}
    let modalOpen = false;
    let editing = null;

    // 검색 상태 (이름/코드)
    let q = '';

    // 폼 상태
    let form = {
        name: '',
        code: '',
        is_excel_encrypted: false,
        excel_data_start_row: 2,
        is_active: true
    };

    // 첫 입력 포커스
    let firstInput;

    // 삭제 확인 모달
    let confirmOpen = false;
    let confirmTarget = null;
    let confirmBusy = false;

    // ===== 유틸 =====
    function setFlash(type, text, ms = 2500) {
        flash = { type, text };
        setTimeout(() => (flash = null), ms);
    }

    // ===== 데이터 로딩 =====
    async function fetchChannels() {
        loading = true;
        error = null;
        try {
            // 백엔드가 /channels?q= 를 지원하면 붙고, 아니면 전체 받아서 프론트 필터
            const url = q ? `/channels?q=${encodeURIComponent(q)}` : '/channels';
            const res = await fetchJson(url);
            if (!res.ok) throw new Error(res.error || '불러오기 실패');
            items = Array.isArray(res.data) ? res.data : [];
            setFlash('info', `채널 ${items.length}개 불러옴`);
        } catch (e) {
            error = e.message || String(e);
        } finally {
            loading = false;
        }
    }

    // 화면 표시용 필터(백엔드가 q 미지원일 경우 대비)
    $: filtered = items.filter((it) => {
        if (!q) return true;
        const s = (q || '').toLowerCase().trim();
        return (it.name || '').toLowerCase().includes(s) || (it.code || '').toLowerCase().includes(s);
    });

    // ===== 모달 제어 =====
    function openNew() {
        editing = null;
        form = {
            name: '',
            code: '',
            is_excel_encrypted: false,
            excel_data_start_row: 2,
            is_active: true
        };
        modalOpen = true;
        queueMicrotask(() => firstInput?.focus());
    }

    function openEdit(item) {
        editing = item;
        form = {
            name: item.name ?? '',
            code: item.code ?? '',
            is_excel_encrypted: !!item.is_excel_encrypted,
            excel_data_start_row: item.excel_data_start_row ?? 2,
            is_active: !!item.is_active
        };
        modalOpen = true;
        queueMicrotask(() => firstInput?.focus());
    }

    function closeModal() {
        modalOpen = false;
        editing = null;
    }

    function onModalKeydown(e) {
        if (e.key === 'Escape') {
            e.stopPropagation();
            closeModal();
        }
    }

    // ===== 저장/삭제/토글 =====
    async function save() {
        const payload = {
            ...form,
            excel_data_start_row: parseInt(form.excel_data_start_row || 2, 10)
        };

        try {
            const url = editing ? `/channels/${editing.id}` : '/channels';
            const method = editing ? 'PUT' : 'POST';
            const res = await fetchJson(url, { method, body: JSON.stringify(payload) });
            if (!res.ok) throw new Error(res.error || '저장 실패');
            setFlash('success', editing ? '채널을 수정했습니다.' : '채널을 등록했습니다.');
            closeModal();
            await fetchChannels();
        } catch (e) {
            setFlash('danger', '저장 실패: ' + (e.message || String(e)));
        }
    }

    function askRemove(item) {
        confirmTarget = item;
        confirmOpen = true;
    }

    async function reallyRemove(item) {
        try {
            const res = await fetchJson(`/channels/${item.id}`, { method: 'DELETE' });
            if (!res.ok) throw new Error(res.error || '삭제 실패');
            setFlash('success', '삭제 완료');
            await fetchChannels();
        } catch (e) {
            setFlash('danger', '삭제 실패: ' + (e.message || String(e)));
        }
    }

    async function toggleActive(item, checked) {
        try {
            const res = await fetchJson(`/channels/${item.id}`, {
                method: 'PUT',
                body: JSON.stringify({
                    code: item.code,
                    name: item.name,
                    is_excel_encrypted: item.is_excel_encrypted,
                    excel_data_start_row: item.excel_data_start_row,
                    is_active: !!checked
                })
            });
            if (!res.ok) throw new Error(res.error || '상태 변경 실패');
            setFlash('success', `활성 상태를 ${checked ? '활성' : '비활성'}으로 변경했습니다.`);
            await fetchChannels();
        } catch (e) {
            setFlash('danger', '상태 변경 실패: ' + (e.message || String(e)));
        }
    }

    // 초기
    fetchChannels();
</script>

<svelte:head>
    <title>채널 관리 · OF Intranet</title>
    <meta name="description" content="주문 수집용 채널(쿠팡/스마트스토어/농협몰 등)을 검색·등록·수정·삭제합니다." />
</svelte:head>

<section class="section" aria-labelledby="pageTitle">
    <div class="container">
        <header class="mb-4">
            <h1 id="pageTitle" class="title is-4">
                <span class="material-icons" aria-hidden="true">share</span>&nbsp;채널 관리
            </h1>
            <p class="subtitle is-6">Excel 업로드 및 주문 수집에 사용되는 채널을 관리합니다.</p>
        </header>

        <!-- Flash / Error -->
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

        <!-- 도구 모음: 등록 + 검색 -->
        <div class="level mb-3" aria-label="도구 모음">
            <div class="level-left">
                <button class="button is-link" type="button" on:click={openNew}>
                    <span class="material-icons" aria-hidden="true">add_circle</span>&nbsp;새 채널 등록
                </button>
            </div>
            <div class="level-right">
                <form class="field has-addons" role="search" aria-label="채널 검색"
                      on:submit|preventDefault={fetchChannels}>
                    <p class="control is-expanded">
                        <label for="q" class="is-hidden">검색어</label>
                        <input id="q" class="input" type="search" bind:value={q}
                               placeholder="채널명 또는 코드로 검색" autocomplete="off" />
                    </p>
                    <p class="control">
                        <button class="button is-info" type="submit" aria-label="검색">
                            <span class="material-icons" aria-hidden="true">search</span>
                        </button>
                    </p>
                    <p class="control">
                        <button class="button" type="button" on:click={() => { q=''; fetchChannels(); }} aria-label="초기화">
                            <span class="material-icons" aria-hidden="true">restart_alt</span>
                        </button>
                    </p>
                </form>
                <span class="tag is-light ml-2" aria-live="polite">총 {filtered.length}개</span>
                {#if loading}<span class="tag is-info ml-2">불러오는 중…</span>{/if}
            </div>
        </div>

        <!-- 목록 -->
        <section aria-labelledby="tableTitle">
            <h2 id="tableTitle" class="is-sr-only">채널 목록</h2>
            <div class="table-container" role="region" aria-label="채널 목록 테이블">
                <table class="table is-fullwidth is-striped is-hoverable channels-table">
                    <caption class="is-sr-only">등록된 채널의 표. 열: ID, 채널명, 코드, 엑셀 암호화, 시작행, 활성(체크박스), 작업</caption>
                    <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">채널명</th>
                        <th scope="col">코드</th>
                        <th scope="col">엑셀 암호화</th>
                        <th scope="col">시작행</th>
                        <th scope="col">활성</th>
                        <th scope="col">작업</th>
                    </tr>
                    </thead>
                    <tbody>
                    {#if filtered.length === 0}
                        <tr><td colspan="7" class="has-text-grey">데이터 없음</td></tr>
                    {:else}
                        {#each filtered as it}
                            <tr>
                                <th scope="row">{it.id}</th>
                                <td>{it.name}</td>
                                <td><span class="tag is-light">{it.code}</span></td>
                                <td>{it.is_excel_encrypted ? '예' : '아니오'}</td>
                                <td>{it.excel_data_start_row}</td>
                                <td class="actions">
                                    <label class="checkbox" aria-label="활성 토글">
                                        <input
                                                type="checkbox"
                                                checked={!!it.is_active}
                                                on:change={(e) => toggleActive(it, e.currentTarget.checked)} />
                                        <span class="ml-1">{it.is_active ? '활성' : '비활성'}</span>
                                    </label>
                                </td>
                                <td class="actions">
                                    <div class="buttons">
                                        <!-- 삭제 -->
                                        <button class="button is-danger is-small" type="button" on:click={() => askRemove(it)} title="삭제" aria-label="삭제">
                                            <span class="material-icons" aria-hidden="true">delete</span>
                                        </button>
                                        <!-- 수정 -->
                                        <button class="button is-info is-small" type="button" on:click={() => openEdit(it)} title="수정" aria-label="수정">
                                            <span class="material-icons" aria-hidden="true">edit</span>
                                        </button>
                                        <!-- 엑셀 검증 룰 -->
                                        <a class="button is-warning is-small" href={`/channels/${it.id}/excel-validations`} title="엑셀 검증 룰" aria-label="엑셀 검증 룰">
                                            <span class="material-icons" aria-hidden="true">rule</span>
                                        </a>
                                        <!-- 엑셀 변환 룰 -->
                                        <a class="button is-link is-light is-small" href={`/channels/${it.id}/excel-transform`} title="엑셀 변환 룰" aria-label="엑셀 변환 룰">
                                            <span class="material-icons" aria-hidden="true">sync_alt</span>
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

<!-- 모달: 등록/수정 -->
{#if modalOpen}
    <div class="modal is-active" role="dialog" aria-modal="true" aria-labelledby="modalTitle" aria-describedby="modalDesc" on:keydown={onModalKeydown}>
        <div class="modal-background" on:click={closeModal}></div>

        <div class="modal-card">
            <header class="modal-card-head">
                <p id="modalTitle" class="modal-card-title">{editing ? '채널 수정' : '채널 등록'}</p>
                <button class="delete" type="button" aria-label="닫기" on:click={closeModal}></button>
            </header>

            <section class="modal-card-body">
                <p id="modalDesc" class="is-sr-only">채널 기본 정보를 입력하고 저장하세요.</p>

                <form on:submit|preventDefault={save} autocomplete="off">
                    <!-- 채널명 -->
                    <div class="field">
                        <label class="label" for="ch-name">채널명</label>
                        <div class="control">
                            <input id="ch-name" class="input" type="text" bind:value={form.name}
                                   required aria-required="true" autocomplete="off" placeholder="예: 네이버 스마트스토어"
                                   bind:this={firstInput} />
                        </div>
                        <p class="help">표시용 이름</p>
                    </div>

                    <!-- 코드 -->
                    <div class="field">
                        <label class="label" for="ch-code">채널 코드</label>
                        <div class="control">
                            <input id="ch-code" class="input" type="text" bind:value={form.code}
                                   required aria-required="true" autocomplete="off" placeholder="예: smartstore" />
                        </div>
                        <p class="help">영문/숫자/하이픈 등 식별용 코드</p>
                    </div>

                    <!-- 암호화 여부 -->
                    <div class="field">
                        <label class="checkbox" for="ch-encrypted">
                            <input id="ch-encrypted" type="checkbox" bind:checked={form.is_excel_encrypted} />
                            엑셀 파일 암호화 여부
                        </label>
                    </div>

                    <!-- 시작 행 -->
                    <div class="field">
                        <label class="label" for="ch-startrow">엑셀 데이터 시작 행</label>
                        <div class="control">
                            <input id="ch-startrow" class="input" type="number" min="1" step="1"
                                   bind:value={form.excel_data_start_row} required aria-required="true" />
                        </div>
                        <p class="help">스마트스토어 등 2행/3행부터 데이터가 시작하는 경우가 있습니다.</p>
                    </div>

                    <!-- 활성 -->
                    <div class="field">
                        <label class="checkbox" for="ch-active">
                            <input id="ch-active" type="checkbox" bind:checked={form.is_active} />
                            활성화
                        </label>
                    </div>

                    <!-- 폼 버튼 -->
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
                <p class="is-size-7 has-text-grey">필수값 누락 시 저장되지 않습니다.</p>
            </footer>
        </div>
    </div>
{/if}

<!-- 삭제 확인 모달 -->
<ConfirmModal
        open={confirmOpen}
        title="채널 삭제"
        message={`채널 <strong>${confirmTarget?.name ?? ''}</strong> 을(를) 삭제하시겠습니까?<br>연관 규칙도 함께 삭제될 수 있습니다.`}
        confirmText="영구 삭제"
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
    /* 테이블 패딩 얇게 */
    .channels-table td,
    .channels-table th { padding: .4rem .6rem; }

    /* 작업 칸: 한 줄 고정 + 최소폭 */
    td.actions {
        white-space: nowrap;
        vertical-align: middle;
        width: 1%;
    }

    /* 버튼 묶음: 줄바꿈 금지 + 아주 작은 간격 */
    td.actions .buttons {
        display: inline-flex;
        flex-wrap: nowrap;
        gap: .25rem;
        margin: 0;
    }

    /* 버튼을 더 슬림하게 */
    td.actions .button.is-small {
        padding: .25rem .45rem;   /* 기본 스몰보다 더 얇게 */
        line-height: 1.1;
        height: auto;             /* Bulma 고정높이 제거 */
    }

    /* 아이콘도 작게 */
    td.actions .material-icons {
        font-size: 18px;
        line-height: 1;
    }
</style>
