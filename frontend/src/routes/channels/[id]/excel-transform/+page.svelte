<script>
    import { onMount } from 'svelte';
    import { page } from '$app/stores';
    import { goto } from '$app/navigation';
    import { fetchJson } from '$lib/api/client.js';
    import { loadChannelMeta, refreshChannelMeta } from '$lib/api/loadChannel.js';
    import Modal from '$lib/components/Modal.svelte';
    import ConfirmModal from '$lib/components/ConfirmModal.svelte';
    import { toast } from '$lib/stores/toast.js';

    // ===== 라우팅 =====
    let channelId; $: channelId = Number($page.params.id);

    // ===== 채널 메타(공통 로더) =====
    let channel = null;
    let channelLoading = false;
    async function initChannel(force = false) {
        channelLoading = true;
        channel = force ? await refreshChannelMeta(channelId) : await loadChannelMeta(channelId);
        channelLoading = false;
    }

    // ===== 상태 =====
    let profile = null;        // 없으면 null
    let loading = false;
    let error = null;

    // 등록/수정 모달
    let modalOpen = false;
    let editing = false;       // true=수정, false=등록
    let form = emptyForm();
    let firstInput;

    // 삭제 확인 모달
    let confirmOpen = false;
    let confirmBusy = false;

    function emptyForm() {
        return {
            tracking_col_ref: '', // 예: G  / G:G / AA:AA
            courier_name: '',
            courier_code: '',
            template_notes: ''
        };
    }

    // ===== 유효성 =====
    const TRACK_RE = /^[A-Z]{1,3}(?::[A-Z]{1,3})?$/; // 'G' 또는 'G:G' 또는 'AA:AA'
    function normalizeColRef(v) {
        return (v || '').toString().trim().toUpperCase();
    }

    function validateForm(payload) {
        if (!TRACK_RE.test(payload.tracking_col_ref)) {
            return '운송장 컬럼(tracking_col_ref) 형식이 올바르지 않습니다. 예: G | G:G | AA:AA';
        }
        if (!payload.courier_name && !payload.courier_code) {
            return '택배사명(courier_name) 또는 택배사코드(courier_code) 중 하나는 반드시 입력해야 합니다.';
        }
        return null;
    }

    // ===== 데이터 로딩 =====
    async function loadProfile() {
        if (!channelId) return;
        loading = true; error = null;
        try {
            const res = await fetchJson(`/channels/${channelId}/excel-transform`);
            if (res.ok) {
                profile = res.data ?? null;
            } else {
                // 백엔드에서 404 not_found일 수 있음 → 존재하지 않는 것으로 간주
                if (res.status === 404) profile = null;
                else throw new Error(res.error || '불러오기 실패');
            }
        } catch (e) {
            error = e.message || String(e);
        } finally {
            loading = false;
        }
    }

    onMount(() => {
        initChannel();
        loadProfile();
    });

    // ===== 모달 열기/닫기 =====
    function openCreate() {
        editing = false;
        form = emptyForm();
        modalOpen = true;
        queueMicrotask(() => firstInput?.focus());
    }
    function openEdit() {
        if (!profile) return;
        editing = true;
        form = {
            tracking_col_ref: profile.tracking_col_ref ?? '',
            courier_name: profile.courier_name ?? '',
            courier_code: profile.courier_code ?? '',
            template_notes: profile.template_notes ?? ''
        };
        modalOpen = true;
        queueMicrotask(() => firstInput?.focus());
    }
    function closeModal() { modalOpen = false; }

    // ===== 저장(등록/수정) =====
    async function save() {
        const payload = {
            tracking_col_ref: normalizeColRef(form.tracking_col_ref),
            courier_name: (form.courier_name || '').trim(),
            courier_code: (form.courier_code || '').trim(),
            template_notes: (form.template_notes || '').trim() || null
        };

        const msg = validateForm(payload);
        if (msg) { toast.danger(msg); return; }

        try {
            const url = `/channels/${channelId}/excel-transform`;
            const method = editing ? 'PUT' : 'POST';
            const res = await fetchJson(url, { method, body: JSON.stringify(payload) });

            if (!res.ok) {
                const first = res.errors && Object.values(res.errors)[0]?.[0];
                toast.danger(first || res.error || '저장 실패');
                return;
            }
            toast.success(editing ? '변환 프로필을 수정했습니다.' : '변환 프로필을 생성했습니다.');
            closeModal();
            await loadProfile();
            await initChannel(true); // 필요 시 상단 채널 정보 갱신
        } catch (e) {
            toast.danger('저장 실패: ' + (e.message || String(e)));
        }
    }

    // ===== 삭제 =====
    function askRemove() { confirmOpen = true; }
    async function reallyRemove() {
        confirmBusy = true;
        try {
            const res = await fetchJson(`/channels/${channelId}/excel-transform`, { method: 'DELETE' });
            if (!res.ok) throw new Error(res.error || '삭제 실패');
            toast.success('변환 프로필을 삭제했습니다.');
            profile = null;
        } catch (e) {
            toast.danger('삭제 실패: ' + (e.message || String(e)));
        } finally {
            confirmBusy = false;
            confirmOpen = false;
        }
    }
</script>

<svelte:head>
    <title>엑셀 변환 프로필 · 채널 {channel ? channel.name : `#${channelId}`} · OF Intranet</title>
    <meta name="description" content="채널별 통합 엑셀 생성을 위한 변환 프로필을 관리합니다." />
</svelte:head>

<section class="section" aria-labelledby="pageTitle">
    <div class="container">
        <header class="mb-4">
            <h1 id="pageTitle" class="title is-4">
                <span class="material-icons" aria-hidden="true">wysiwyg</span>&nbsp;엑셀 변환 프로필
            </h1>

            <!-- 대상 채널 표시 (공통 스타일) -->
            <p class="subtitle is-6">
                대상 채널:&nbsp;
                {#if channelLoading}
                    <span class="tag is-light">불러오는 중…</span>
                {:else if channel}
                    <strong>{channel.name}</strong>
                    <span class="tag is-light ml-2 mono">{channel.code}</span>
                    <span class="tag is-info is-light ml-1">ID {channelId}</span>
                {:else}
                    <span class="has-text-grey">알 수 없음 (ID {channelId})</span>
                {/if}
            </p>

            <nav class="breadcrumb is-small" aria-label="breadcrumbs">
                <ul>
                    <li><a href="/channels">채널 관리</a></li>
                    <li class="is-active">
                        <a aria-current="page">
                            {channel ? channel.name : `채널 ${channelId}`} · 엑셀 변환 프로필
                        </a>
                    </li>
                </ul>
            </nav>
        </header>

        {#if error}
            <article class="message is-danger" aria-live="polite">
                <div class="message-body"><strong>오류:</strong> {error}</div>
            </article>
        {/if}

        <!-- 도구줄 -->
        <div class="level mb-3">
            <div class="level-left" style="gap:.5rem;">
                {#if profile}
                    <button class="button is-link" type="button" on:click={openEdit}>
                        <span class="material-icons" aria-hidden="true">edit</span>&nbsp;프로필 수정
                    </button>
                    <button class="button is-danger is-light" type="button" on:click={askRemove}>
                        <span class="material-icons" aria-hidden="true">delete</span>&nbsp;프로필 삭제
                    </button>
                {:else}
                    <button class="button is-link" type="button" on:click={openCreate}>
                        <span class="material-icons" aria-hidden="true">add_circle</span>&nbsp;프로필 생성
                    </button>
                {/if}
                <a class="button" href={`/channels/${channelId}/excel-validations`}>
                    <span class="material-icons" aria-hidden="true">rule</span>&nbsp;검증 규칙
                </a>
                <a class="button" href={`/channels/${channelId}/field-mappings`}>
                    <span class="material-icons" aria-hidden="true">view_column</span>&nbsp;필드 매핑
                </a>
            </div>
            <div class="level-right" style="gap:.5rem; align-items:center;">
                {#if loading}<span class="tag is-info">불러오는 중…</span>{/if}
            </div>
        </div>

        <!-- 내용 -->
        {#if !loading && !profile}
            <div class="notification is-light">
                아직 저장된 변환 프로필이 없습니다. <strong>프로필 생성</strong>을 눌러 등록하세요.
            </div>
        {:else if profile}
            <div class="card">
                <div class="card-content">
                    <div class="content">
                        <h2 class="title is-6">현재 프로필</h2>
                        <div class="tags" style="gap:.5rem;">
                            <span class="tag is-dark">
                                <span class="material-icons md-18" aria-hidden="true">view_column</span>
                                &nbsp;운송장 컬럼&nbsp;<code class="mono">{profile.tracking_col_ref}</code>
                            </span>

                            {#if profile.courier_name}
                                <span class="tag is-link is-light">
                                    <span class="material-icons md-18" aria-hidden="true">local_shipping</span>
                                    &nbsp;택배사명&nbsp;<strong>{profile.courier_name}</strong>
                                </span>
                            {/if}

                            {#if profile.courier_code}
                                <span class="tag is-info is-light">
                                    <span class="material-icons md-18" aria-hidden="true">qr_code_2</span>
                                    &nbsp;택배사코드&nbsp;<code class="mono">{profile.courier_code}</code>
                                </span>
                            {/if}

                            {#if profile.template_notes}
                                <span class="tag is-warning is-light" title={profile.template_notes}>
                                    <span class="material-icons md-18" aria-hidden="true">sticky_note_2</span>
                                    &nbsp;메모
                                </span>
                            {/if}
                        </div>

                        {#if profile.template_notes}
                            <div class="box is-shadowless is-radiusless mt-3">
                                <p class="is-size-7 has-text-grey">메모</p>
                                <pre class="mono" style="white-space:pre-wrap;">{profile.template_notes}</pre>
                            </div>
                        {/if}
                    </div>
                </div>
                <footer class="card-footer">
                    <a class="card-footer-item" role="button" on:click={openEdit}>
                        <span class="material-icons" aria-hidden="true">edit</span>&nbsp;수정
                    </a>
                    <a class="card-footer-item" role="button" on:click={askRemove}>
                        <span class="material-icons" aria-hidden="true">delete</span>&nbsp;삭제
                    </a>
                </footer>
            </div>
        {/if}

        <div class="mt-4">
            <button class="button" type="button" on:click={() => goto('/channels')}>
                <span class="material-icons" aria-hidden="true">arrow_back</span>&nbsp;채널 목록으로
            </button>
        </div>
    </div>
</section>

<!-- 등록/수정 모달 -->
<Modal
        open={modalOpen}
        title={editing ? '변환 프로필 수정' : '변환 프로필 생성'}
        ariaDescription="통합 엑셀 생성 시 사용할 운송장 컬럼과 택배사 정보를 설정합니다."
        width={680}
        maxHeight={720}
        draggable={true}
        on:close={closeModal}
>
    <svelte:fragment slot="body">
        <form on:submit|preventDefault={save} autocomplete="off">
            <div class="columns is-multiline">
                <div class="column is-6">
                    <div class="field">
                        <label class="label" for="tf-col">tracking_col_ref</label>
                        <div class="control">
                            <input id="tf-col" class="input" type="text" bind:value={form.tracking_col_ref}
                                   placeholder="예: G  |  G:G  |  AA:AA"
                                   required aria-required="true" bind:this={firstInput}
                                   on:blur={() => form.tracking_col_ref = normalizeColRef(form.tracking_col_ref)} />
                        </div>
                        <p class="help">형식: A~ZZZ, 선택적으로 <code>:</code>로 동일 열 반복 (예: G:G)</p>
                    </div>
                </div>

                <div class="column is-6">
                    <div class="field">
                        <label class="label" for="tf-name">courier_name (택배사명)</label>
                        <div class="control">
                            <input id="tf-name" class="input" type="text" bind:value={form.courier_name}
                                   placeholder="예: 우체국택배 / CJ대한통운" />
                        </div>
                        <p class="help">코드 대신 이름만 입력해도 됩니다.</p>
                    </div>
                </div>

                <div class="column is-6">
                    <div class="field">
                        <label class="label" for="tf-code">courier_code (택배사코드)</label>
                        <div class="control">
                            <input id="tf-code" class="input" type="text" bind:value={form.courier_code}
                                   placeholder="예: 9002" />
                        </div>
                        <p class="help">이름 대신 코드만 입력해도 됩니다.</p>
                    </div>
                </div>

                <div class="column is-12">
                    <div class="field">
                        <label class="label" for="tf-notes">template_notes (메모)</label>
                        <div class="control">
                            <textarea id="tf-notes" class="textarea mono" rows="5" bind:value={form.template_notes}
                                      placeholder="예: 스마트스토어 서식 기준 / 운송장 열은 H:H로 바뀌는 경우 있음"></textarea>
                        </div>
                    </div>
                </div>

                <div class="column is-12">
                    <div class="notification is-light">
                        <ul style="margin-left:1rem; list-style:disc;">
                            <li><code>tracking_col_ref</code>는 필수, <strong>대문자</strong>로 입력됩니다.</li>
                            <li><code>courier_name</code>과 <code>courier_code</code>는 <strong>둘 중 하나만</strong> 필수입니다.</li>
                        </ul>
                    </div>
                </div>
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

<!-- 삭제 확인 -->
<ConfirmModal
        open={confirmOpen}
        title="변환 프로필 삭제"
        message="이 채널의 변환 프로필을 삭제하시겠습니까?"
        confirmText="영구 삭제"
        cancelText="취소"
        confirmClass="is-danger"
        busy={confirmBusy}
        draggable={true}
        on:confirm={reallyRemove}
        on:cancel={() => { confirmOpen = false; }}
/>
