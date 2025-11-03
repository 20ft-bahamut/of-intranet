<script>
    import { onMount } from 'svelte';
    import { page } from '$app/stores';
    import { fetchJson } from '$lib/api/client.js';
    import Modal from '$lib/components/Modal.svelte';
    import ConfirmModal from '$lib/components/ConfirmModal.svelte';
    import NoteBadge from '$lib/components/NoteBadge.svelte';
    import { toast } from '$lib/stores/toast.js';

    // ===== 파라미터 =====
    let channelId;
    $: channelId = Number(($page?.params?.id) ?? 0);

    // ===== 상단 표시용 채널 =====
    let channel = null; // {id,name,code,...}

    // ===== 프로필 (채널당 1건) =====
    let profile = null; // {id,tracking_col_ref,courier_name,courier_code,template_notes,...} | null
    let loading = false;
    let error = null;

    // ===== 모달(등록/수정) =====
    let modalOpen = false;
    let editing = false; // false:새로등록, true:수정
    let firstInput;

    function emptyForm(){
        return {
            tracking_col_ref: '',
            courier_name: '',
            courier_code: '',
            template_notes: ''
        };
    }
    let form = emptyForm();

    // 삭제 확인
    let confirmOpen = false;
    let confirmBusy = false;

    // 정규식: A ~ ZZZ 혹은 범위 A:C / G:G 식
    const trackingRe = /^[A-Z]{1,3}(?::[A-Z]{1,3})?$/;

    // ===== 초기 로드 =====
    onMount(async () => {
        await loadChannel();
        await loadProfile();
    });

    async function loadChannel() {
        const res = await fetchJson(`/channels/${channelId}`);
        if (res.ok) channel = res.data;
    }

    async function loadProfile() {
        loading = true; error = null;
        try {
            const res = await fetchJson(`/channels/${channelId}/excel-transform`);
            if (!res.ok) {
                // 404면 프로필 없음으로 간주
                if (res.status === 404) { profile = null; return; }
                throw new Error(res.error || '불러오기 실패');
            }
            profile = res.data ?? null;
        } catch (e) {
            error = e.message || String(e);
        } finally {
            loading = false;
        }
    }

    // ===== 모달 =====
    function openNew(){
        editing = false;
        form = emptyForm();
        modalOpen = true;
        queueMicrotask(()=>firstInput?.focus());
    }
    function openEdit(){
        if (!profile) return;
        editing = true;
        form = {
            tracking_col_ref: profile.tracking_col_ref ?? '',
            courier_name: profile.courier_name ?? '',
            courier_code: profile.courier_code ?? '',
            template_notes: profile.template_notes ?? ''
        };
        modalOpen = true;
        queueMicrotask(()=>firstInput?.focus());
    }
    function closeModal(){ modalOpen = false; }

    // ===== 저장 =====
    async function save(){
        // 클라 검증
        const tcol = (form.tracking_col_ref || '').trim().toUpperCase();
        const name = (form.courier_name || '').trim();
        const code = (form.courier_code || '').trim();
        if (!trackingRe.test(tcol)) {
            toast.danger('운송장 컬럼 값이 올바르지 않습니다. 예: G 또는 G:G 또는 AA:AA');
            return;
        }
        if (!name && !code) {
            toast.danger('택배사명과 택배사코드 중 하나는 반드시 입력하세요.');
            return;
        }

        const payload = {
            tracking_col_ref: tcol,
            courier_name: name,
            courier_code: code,
            template_notes: (form.template_notes || '').trim()
        };

        try {
            const url = `/channels/${channelId}/excel-transform`;
            const method = editing ? 'PUT' : 'POST';
            const res = await fetchJson(url, { method, body: JSON.stringify(payload) });
            if (!res.ok) {
                const first = res.errors && Object.values(res.errors)[0]?.[0];
                toast.danger(first || res.error || '저장 실패');
                return;
            }
            toast.success(editing ? '변환 프로필을 수정했습니다.' : '변환 프로필을 등록했습니다.');
            closeModal();
            await loadProfile();
        } catch (e) {
            toast.danger('저장 실패: ' + (e.message || String(e)));
        }
    }

    // ===== 삭제 =====
    async function reallyRemove(){
        try{
            const res = await fetchJson(`/channels/${channelId}/excel-transform`, { method: 'DELETE' });
            if (!res.ok) throw new Error(res.error || '삭제 실패');
            toast.success('변환 프로필을 삭제했습니다.');
            await loadProfile();
        }catch(e){
            toast.danger('삭제 실패: ' + (e.message || String(e)));
        }
    }
</script>

<svelte:head>
    <title>엑셀 변환 프로필 · OF Intranet</title>
    <meta name="description" content="채널별 통합 엑셀 생성 시 운송장 컬럼/택배사 정보 등을 관리합니다." />
</svelte:head>

<section class="section" aria-labelledby="pageTitle">
    <div class="container">
        <header class="mb-4">
            <h1 id="pageTitle" class="title is-4">
                <span class="material-icons" aria-hidden="true">sync_alt</span>&nbsp;엑셀 변환 프로필
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

        <!-- 현재 상태 카드 -->
        <div class="card">
            <header class="card-header">
                <p class="card-header-title">현재 프로필</p>
                <div class="card-header-icon" style="gap:.5rem; padding-right:.75rem;">
                    {#if profile}
                        <button class="button is-info is-small" type="button" on:click={openEdit}>
                            <span class="material-icons">edit</span>&nbsp;수정
                        </button>
                        <button class="button is-danger is-light is-small" type="button" on:click={() => (confirmOpen = true)}>
                            <span class="material-icons">delete</span>&nbsp;삭제
                        </button>
                    {:else}
                        <button class="button is-link is-small" type="button" on:click={openNew}>
                            <span class="material-icons">add_circle</span>&nbsp;등록
                        </button>
                    {/if}
                </div>
            </header>

            <div class="card-content">
                {#if loading}
                    <span class="tag is-info">불러오는 중…</span>
                {:else if !profile}
                    <p class="has-text-grey">등록된 변환 프로필이 없습니다. <button class="button is-text is-small" on:click={openNew}>지금 등록</button></p>
                {:else}
                    <div class="content" style="display:flex; flex-wrap:wrap; gap:.5rem;">
                        <span class="tag is-link">운송장 컬럼: <strong class="ml-1">{profile.tracking_col_ref}</strong></span>
                        {#if profile.courier_name}
                            <span class="tag is-warning">택배사명: <strong class="ml-1">{profile.courier_name}</strong></span>
                        {/if}
                        {#if profile.courier_code}
                            <span class="tag is-warning is-light">택배사코드: <strong class="ml-1">{profile.courier_code}</strong></span>
                        {/if}
                        <NoteBadge note={profile.template_notes} title="메모" />
                    </div>
                {/if}
            </div>
        </div>

        <div class="mt-4">
            <a class="button" href="/channels">
                <span class="material-icons">arrow_back</span>&nbsp;채널 목록
            </a>
        </div>
    </div>
</section>

<!-- 등록/수정 모달 (공통 Modal 사용) -->
<Modal
        open={modalOpen}
        title={editing ? '변환 프로필 수정' : '변환 프로필 등록'}
        ariaDescription="운송장 컬럼과 택배사 정보를 입력하세요."
        width={640}
        maxHeight={560}
        draggable={true}
        on:close={() => (modalOpen = false)}
>
    <svelte:fragment slot="body">
        <form on:submit|preventDefault={save} autocomplete="off">
            <!-- 운송장 컬럼 -->
            <div class="field">
                <label class="label" for="t-col">운송장 컬럼 (tracking_col_ref)</label>
                <div class="control">
                    <input id="t-col" class="input" type="text" bind:value={form.tracking_col_ref}
                           required aria-required="true" placeholder="예: G 또는 G:G 또는 AA:AA"
                           bind:this={firstInput}
                           on:blur={() => form.tracking_col_ref = (form.tracking_col_ref||'').toUpperCase()} />
                </div>
                <p class="help">형식: <code>^[A-Z]{1,3}(:[A-Z]{1,3})?$</code> — 단일 열 또는 범위 표기(G 또는 G:G)</p>
            </div>

            <!-- 택배사명 / 코드 (둘 중 하나 필수) -->
            <div class="columns is-variable is-2">
                <div class="column">
                    <div class="field">
                        <label class="label" for="t-name">택배사명</label>
                        <div class="control">
                            <input id="t-name" class="input" type="text" bind:value={form.courier_name} placeholder="예: 우체국택배" />
                        </div>
                        <p class="help">택배사명 또는 코드 중 하나는 반드시 입력</p>
                    </div>
                </div>
                <div class="column">
                    <div class="field">
                        <label class="label" for="t-code">택배사코드</label>
                        <div class="control">
                            <input id="t-code" class="input" type="text" bind:value={form.courier_code} placeholder="예: 9002" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- 템플릿 메모 -->
            <div class="field">
                <label class="label" for="t-notes">템플릿 메모</label>
                <div class="control">
                    <textarea id="t-notes" class="textarea" rows="3" bind:value={form.template_notes} placeholder="출력 엑셀 템플릿 관련 메모"></textarea>
                </div>
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
                <button class="button" type="button" on:click={() => (modalOpen = false)}>취소</button>
            </div>
        </div>
    </svelte:fragment>
</Modal>

<!-- 삭제 확인 (드래그 가능) -->
<ConfirmModal
        open={confirmOpen}
        title="변환 프로필 삭제"
        message="현재 채널의 변환 프로필을 삭제하시겠습니까?"
        confirmText="영구 삭제"
        cancelText="취소"
        confirmClass="is-danger"
        busy={confirmBusy}
        draggable={true}
        on:confirm={async () => {
    confirmBusy = true;
    await reallyRemove();
    confirmBusy = false;
    confirmOpen = false;
  }}
        on:cancel={() => { confirmOpen = false; }}
/>

<style>
    /* 이 페이지 전용: 카드 안 태그 간격 유지 */
    .card .content .tag { margin-right: .25rem; }
</style>
