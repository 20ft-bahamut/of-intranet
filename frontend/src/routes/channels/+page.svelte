<script>
    import { fetchJson, qs } from '$lib/api/client.js';
    import SearchBar from '$lib/components/SearchBar.svelte';
    import Modal from '$lib/components/Modal.svelte';
    import ConfirmModal from '$lib/components/ConfirmModal.svelte';
    import { toast } from '$lib/stores/toast.js';

    // ===== 상태 =====
    let items = [];
    let loading = false;
    let error = null;

    // 검색/필터
    let q = '';
    let onlyActive = ''; // '' | '1' | '0'

    // 모달
    let modalOpen = false;
    let editing = null;
    let firstInput;

    function emptyForm() {
        return {
            name: '',
            code: '',
            is_excel_encrypted: false,
            excel_data_start_row: 2,
            is_active: true
        };
    }
    let form = emptyForm();

    // 삭제 확인
    let confirmOpen = false;
    let confirmTarget = null;
    let confirmBusy = false;

    // ===== 데이터 로딩 =====
    async function load() {
        loading = true; error = null;
        try {
            const res = await fetchJson(
                '/channels' + qs({ q: q || undefined, is_active: onlyActive === '' ? undefined : onlyActive })
            );
            if (!res.ok) throw new Error(res.error || '불러오기 실패');
            items = Array.isArray(res.data) ? res.data : [];
            toast.info(`채널 ${items.length}개 불러옴`);
        } catch (e) {
            error = e.message || String(e);
        } finally {
            loading = false;
        }
    }
    load();

    // ===== 모달 제어 =====
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
            is_excel_encrypted: !!row.is_excel_encrypted,
            excel_data_start_row: row.excel_data_start_row ?? 2,
            is_active: !!row.is_active
        };
        modalOpen = true;
        queueMicrotask(() => firstInput?.focus());
    }
    function closeModal(){ modalOpen = false; editing = null; }

    // ===== 저장 =====
    async function save() {
        const payload = {
            ...form,
            excel_data_start_row: parseInt(form.excel_data_start_row || 2, 10)
        };
        try {
            const url = editing ? `/channels/${editing.id}` : '/channels';
            const method = editing ? 'PUT' : 'POST';
            const res = await fetchJson(url, { method, body: JSON.stringify(payload) });

            if (!res.ok) {
                const first = res.errors && Object.values(res.errors)[0]?.[0];
                toast.danger(first || res.error || '저장 실패');
                return;
            }
            toast.success(editing ? '채널을 수정했습니다.' : '채널을 등록했습니다.');
            closeModal();
            await load();
        } catch (e) {
            toast.danger('저장 실패: ' + (e.message || String(e)));
        }
    }

    // ===== 삭제 =====
    function askRemove(row){ confirmTarget = row; confirmOpen = true; }
    async function reallyRemove(row){
        try{
            const res = await fetchJson(`/channels/${row.id}`, { method:'DELETE' });
            if(!res.ok) throw new Error(res.error || '삭제 실패');
            toast.success('삭제 완료'); await load();
        }catch(e){ toast.danger('삭제 실패: ' + (e.message || String(e))); }
    }

    // ===== 활성 토글 =====
    async function toggleActive(row, checked){
        try{
            const res = await fetchJson(`/channels/${row.id}`, {
                method:'PUT',
                body: JSON.stringify({
                    name: row.name,
                    code: row.code,
                    is_excel_encrypted: row.is_excel_encrypted,
                    excel_data_start_row: row.excel_data_start_row,
                    is_active: !!checked
                })
            });
            if(!res.ok) throw new Error(res.error || '상태 변경 실패');
            toast.success(`활성 상태를 ${checked ? '활성' : '비활성'}으로 변경했습니다.`);
            await load();
        }catch(e){ toast.danger('상태 변경 실패: ' + (e.message || String(e))); }
    }

    // 검색바 이벤트
    function doSearch(){ load(); }
    function doReset(){ q=''; onlyActive=''; load(); }
</script>

<svelte:head>
    <title>채널 관리 · OF Intranet</title>
    <meta name="description" content="주문 수집에 사용되는 채널을 검색·등록·수정·삭제합니다." />
</svelte:head>

<section class="section" aria-labelledby="pageTitle">
    <div class="container">
        <header class="mb-4">
            <h1 id="pageTitle" class="title is-4">
                <span class="material-icons" aria-hidden="true">share</span>&nbsp;채널 관리
            </h1>
            <p class="subtitle is-6">Excel 업로드 및 주문 수집에 사용되는 채널을 관리합니다.</p>
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
                    <span class="material-icons" aria-hidden="true">add_circle</span>&nbsp;새 채널 등록
                </button>
            </div>

            <div class="level-right" style="gap:.5rem; align-items:center;">
                <SearchBar
                        bind:q
                        placeholder="채널명 또는 코드로 검색"
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
        <div class="table-container" role="region" aria-label="채널 목록 테이블">
            <table class="table is-fullwidth is-striped is-hoverable channels-table">
                <caption class="is-sr-only">
                    등록된 채널의 표. 열: ID, 채널명, 코드, 엑셀 암호화, 시작행, 활성, 작업
                </caption>
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
                {#if items.length === 0}
                    <tr><td colspan="7" class="has-text-grey">데이터 없음</td></tr>
                {:else}
                    {#each items as it}
                        <tr>
                            <th scope="row">{it.id}</th>
                            <td>{it.name}</td>
                            <td><span class="tag is-light">{it.code}</span></td>
                            <td>{it.is_excel_encrypted ? '예' : '아니오'}</td>
                            <td>{it.excel_data_start_row}</td>
                            <td class="actions">
                                <label class="checkbox" aria-label="활성 토글">
                                    <input type="checkbox" checked={!!it.is_active}
                                           on:change={(e)=>toggleActive(it, e.currentTarget.checked)} />
                                    <span class="ml-1">{it.is_active ? '활성' : '비활성'}</span>
                                </label>
                            </td>
                            <td class="actions">
                                <div class="buttons no-wrap">
                                    <button class="button is-danger is-small" type="button" on:click={() => askRemove(it)} title="삭제" aria-label="삭제">
                                        <span class="material-icons" aria-hidden="true">delete</span>
                                    </button>
                                    <button class="button is-info is-small" type="button" on:click={() => openEdit(it)} title="수정" aria-label="수정">
                                        <span class="material-icons" aria-hidden="true">edit</span>
                                    </button>
                                    <a class="button is-warning is-small" href={`/channels/${it.id}/excel-validations`} title="엑셀 검증 룰" aria-label="엑셀 검증 룰">
                                        <span class="material-icons" aria-hidden="true">rule</span>
                                    </a>
                                    <a class="button is-link is-light is-small" href={`/channels/${it.id}/excel-transform`} title="엑셀 변환 룰" aria-label="엑셀 변환 룰">
                                        <span class="material-icons" aria-hidden="true">sync_alt</span>
                                    </a>
                                    <!-- ✅ 신규: 필드 매핑 진입 -->
                                    <a class="button is-primary is-light is-small" href={`/channels/${it.id}/field-mappings`} title="필드 매핑" aria-label="필드 매핑">
                                        <span class="material-icons" aria-hidden="true">view_column</span>
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
        title={editing ? '채널 수정' : '채널 등록'}
        ariaDescription="채널 기본 정보를 입력하고 저장하세요."
        width={640}
        maxHeight={640}
        draggable={true}
        on:close={closeModal}
>
    <svelte:fragment slot="body">
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
                    <span class="ml-1">엑셀 파일 암호화 여부</span>
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

<!-- 삭제 확인 모달 (드래그 가능) -->
<ConfirmModal
        open={confirmOpen}
        title="채널 삭제"
        message={`채널 <strong>${confirmTarget?.name ?? ''}</strong> 을(를) 삭제하시겠습니까?<br>연관 규칙도 함께 삭제될 수 있습니다.`}
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
    /* 이 페이지 전용: 표 패딩만 살짝 컴팩트 */
    .channels-table td, .channels-table th { padding: .4rem .6rem; }
</style>
