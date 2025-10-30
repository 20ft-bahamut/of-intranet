<script>
    import { page } from '$app/stores';
    import { fetchJson } from '$lib/api/client.js';
    import ConfirmModal from '$lib/components/ConfirmModal.svelte';

    // 라우트 파라미터
    $: channelId = $page.params.id;

    // 상태
    let channel = null;
    let profile = null;         // 존재하면 객체, 없으면 null
    let loading = false;
    let error = null;
    let flash = null;           // {type,text}

    // 폼 (채널당 1건이라 단일 폼)
    let form = {
        tracking_col_ref: '',
        courier_name: '',
        courier_code: '',
        template_notes: ''
    };
    let firstInput;

    // 삭제 확인 모달
    let confirmOpen = false;
    let confirmBusy = false;

    // 유틸
    function setFlash(type, text, ms = 2600) {
        flash = { type, text };
        setTimeout(() => (flash = null), ms);
    }

    // tracking_col_ref: "G" 또는 "G:G"
    const COL_RX = /^[A-Z]{1,3}(:[A-Z]{1,3})?$/;

    function validate() {
        const col = (form.tracking_col_ref || '').trim().toUpperCase();
        if (!COL_RX.test(col)) return '송장번호 열 위치는 G 또는 G:G 형식으로 입력하세요.';
        const hasName = !!String(form.courier_name || '').trim();
        const hasCode = !!String(form.courier_code || '').trim();
        if (!hasName && !hasCode) return '택배사명 또는 택배사 코드 중 하나는 반드시 입력하세요.';
        return null;
    }

    // API
    async function fetchChannel() {
        const res = await fetchJson(`/channels/${channelId}`);
        if (!res.ok) throw new Error(res.error || '채널 정보 조회 실패');
        channel = res.data;
    }

    async function fetchProfile() {
        const res = await fetchJson(`/channels/${channelId}/excel-transform`);
        if (!res.ok) {
            // 404를 error로 내려주면 빈 프로필 취급
            profile = null;
            return;
        }
        profile = res.data ?? null;
    }

    async function fetchAll() {
        loading = true; error = null;
        try {
            await Promise.all([fetchChannel(), fetchProfile()]);
            if (profile) {
                form = {
                    tracking_col_ref: profile.tracking_col_ref ?? '',
                    courier_name: profile.courier_name ?? '',
                    courier_code: profile.courier_code ?? '',
                    template_notes: profile.template_notes ?? ''
                };
            } else {
                form = { tracking_col_ref: '', courier_name: '', courier_code: '', template_notes: '' };
            }
            setFlash('info', profile ? '프로필 불러옴' : '프로필 없음 (새로 생성하세요)');
            queueMicrotask(() => firstInput?.focus());
        } catch (e) {
            error = e.message || String(e);
        } finally {
            loading = false;
        }
    }

    async function save() {
        const err = validate();
        if (err) return setFlash('warning', err, 3200);

        const payload = {
            tracking_col_ref: form.tracking_col_ref.trim().toUpperCase(),
            courier_name: String(form.courier_name || '').trim(),
            courier_code: String(form.courier_code || '').trim(),
            template_notes: String(form.template_notes || '').trim()
        };

        try {
            const url = profile
                ? `/channels/${channelId}/excel-transform`
                : `/channels/${channelId}/excel-transform`;
            const method = profile ? 'PUT' : 'POST';
            const res = await fetchJson(url, { method, body: JSON.stringify(payload) });
            if (!res.ok) throw new Error(res.error || '저장 실패');
            setFlash('success', profile ? '프로필을 수정했습니다.' : '프로필을 생성했습니다.');
            await fetchProfile();
        } catch (e) {
            setFlash('danger', '저장 실패: ' + (e.message || String(e)));
        }
    }

    async function reallyRemove() {
        try {
            const res = await fetchJson(`/channels/${channelId}/excel-transform`, { method: 'DELETE' });
            if (!res.ok) throw new Error(res.error || '삭제 실패');
            setFlash('success', '프로필 삭제 완료');
            profile = null;
            form = { tracking_col_ref: '', courier_name: '', courier_code: '', template_notes: '' };
        } catch (e) {
            setFlash('danger', '삭제 실패: ' + (e.message || String(e)));
        }
    }

    // 초기 로드
    $: fetchAll();
</script>

<svelte:head>
    <title>엑셀 변환 프로필 · 채널 #{channelId} · OF Intranet</title>
    <meta name="description" content="채널별 송장 자동입력용 엑셀 생성 프로필(열 위치/택배사)을 관리합니다." />
</svelte:head>

<section class="section" aria-labelledby="pageTitle">
    <div class="container">
        <!-- Breadcrumb -->
        <nav class="breadcrumb" aria-label="breadcrumbs">
            <ul>
                <li><a href="/channels">채널 관리</a></li>
                <li class="is-active"><a aria-current="page">엑셀 변환 프로필</a></li>
            </ul>
        </nav>

        <header class="mb-4">
            <h1 id="pageTitle" class="title is-4">
                <span class="material-icons" aria-hidden="true">sync_alt</span>&nbsp;엑셀 변환 프로필
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

        <!-- 툴바 -->
        <div class="level mb-3" aria-label="도구 모음">
            <div class="level-left">
                <a class="button" href="/channels">
                    <span class="material-icons" aria-hidden="true">arrow_back</span>&nbsp;목록으로
                </a>
            </div>
            <div class="level-right">
                {#if profile}
                    <span class="tag is-light mr-2">ID {profile.id}</span>
                {/if}
                {#if loading}<span class="tag is-info ml-2">불러오는 중…</span>{/if}
            </div>
        </div>

        <!-- 단일 폼 -->
        <section aria-labelledby="formTitle">
            <h2 id="formTitle" class="is-sr-only">엑셀 변환 프로필 폼</h2>

            <form class="box" on:submit|preventDefault={save} autocomplete="off" aria-describedby="formHelp">
                <div class="columns is-multiline">
                    <!-- tracking_col_ref -->
                    <div class="column is-12-tablet is-4-desktop">
                        <div class="field">
                            <label class="label" for="f-col">송장번호 열 위치</label>
                            <div class="control">
                                <input id="f-col" class="input" type="text" placeholder="예: G 또는 G:G"
                                       bind:value={form.tracking_col_ref} required aria-required="true"
                                       bind:this={firstInput} />
                            </div>
                            <p class="help">엑셀에서 송장번호가 들어갈 열. 단일 열(<code>G</code>) 또는 열 범위(<code>G:G</code>)</p>
                        </div>
                    </div>

                    <!-- courier_name -->
                    <div class="column is-12-tablet is-4-desktop">
                        <div class="field">
                            <label class="label" for="f-name">택배사명(선택)</label>
                            <div class="control">
                                <input id="f-name" class="input" type="text" placeholder="예: 우체국택배"
                                       bind:value={form.courier_name} />
                            </div>
                        </div>
                    </div>

                    <!-- courier_code -->
                    <div class="column is-12-tablet is-4-desktop">
                        <div class="field">
                            <label class="label" for="f-code">택배사 코드(선택)</label>
                            <div class="control">
                                <input id="f-code" class="input" type="text" placeholder="예: 9002"
                                       bind:value={form.courier_code} />
                            </div>
                            <p class="help">택배사명 또는 코드 중 최소 1개는 필수</p>
                        </div>
                    </div>

                    <!-- notes -->
                    <div class="column is-12">
                        <div class="field">
                            <label class="label" for="f-notes">메모(선택)</label>
                            <div class="control">
                                <input id="f-notes" class="input" type="text" maxlength="255"
                                       placeholder="템플릿 설명 또는 주의사항" bind:value={form.template_notes} />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="field is-grouped">
                    <div class="control">
                        <button class="button is-link" type="submit">
                            <span class="material-icons" aria-hidden="true">save</span>&nbsp;{profile ? '수정 저장' : '생성'}
                        </button>
                    </div>
                    {#if profile}
                        <div class="control">
                            <button class="button is-danger" type="button" on:click={() => (confirmOpen = true)}>
                                <span class="material-icons" aria-hidden="true">delete</span>&nbsp;삭제
                            </button>
                        </div>
                    {/if}
                </div>

                <p id="formHelp" class="help">저장 시 채널당 1개 프로필이 유지됩니다(UNIQUE).</p>
            </form>

            <!-- 현재 값 미니 요약 -->
            <article class="message is-light">
                <div class="message-body">
                    <strong>현재 상태</strong>:
                    열 = <span class="tag is-info is-light">{profile ? profile.tracking_col_ref : '—'}</span>,
                    택배사명 = <span class="tag is-light">{profile?.courier_name || '—'}</span>,
                    코드 = <span class="tag is-light">{profile?.courier_code || '—'}</span>
                    {#if profile?.template_notes}
                        &nbsp;· 메모: {profile.template_notes}
                    {/if}
                </div>
            </article>
        </section>
    </div>
</section>

<!-- 삭제 확인 모달 -->
<ConfirmModal
        open={confirmOpen}
        title="프로필 삭제"
        message="엑셀 변환 프로필을 삭제하시겠습니까?"
        confirmText="삭제"
        cancelText="취소"
        confirmClass="is-danger"
        busy={confirmBusy}
        on:confirm={async () => {
    confirmBusy = true;
    await reallyRemove();
    confirmBusy = false;
    confirmOpen = false;
  }}
        on:cancel={() => { confirmOpen = false; }}
/>

<style>
    .box { padding: 1.25rem; }
</style>
