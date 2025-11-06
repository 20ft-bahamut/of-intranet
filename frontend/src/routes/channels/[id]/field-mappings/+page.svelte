<script>
    import { onMount } from 'svelte';
    import { page } from '$app/stores';
    import { goto } from '$app/navigation';
    import { fetchJson, qs } from '$lib/api/client.js';
    import { loadChannelMeta, refreshChannelMeta } from '$lib/api/loadChannel.js';
    import SearchBar from '$lib/components/SearchBar.svelte';
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

    // ===== 목록 상태 =====
    let items = [];
    let loading = false;
    let error = null;
    let total = 0;

    // 검색
    let q = '';

    // ===== 등록/수정 모달 =====
    let modalOpen = false;
    let editing = null;
    let form = emptyForm();
    let optionsText = ''; // options JSON textarea
    let firstInput;

    function emptyForm() {
        return {
            field_key: '',
            selector_type: 'col_ref', // col_ref|header_text|regex|expr
            selector_value: '',
            options: null
        };
    }

    // ===== 삭제 모달 =====
    let confirmOpen = false;
    let confirmTarget = null;
    let confirmBusy = false;

    // ===== 데이터 로드 =====
    async function loadList() {
        if (!channelId) return;
        loading = true; error = null;
        try {
            const res = await fetchJson(`/channels/${channelId}/field-mappings` + qs({ q: q || undefined }));
            if (!res.ok) throw new Error(res.error || '불러오기 실패');
            items = Array.isArray(res.data) ? res.data : [];
            total = items.length;
        } catch (e) {
            error = e.message || String(e);
        } finally {
            loading = false;
        }
    }

    onMount(() => {
        initChannel();
        loadList();
    });

    // ===== 모달 제어 =====
    function openNew() {
        editing = null;
        form = emptyForm();
        optionsText = '';
        modalOpen = true;
        queueMicrotask(() => firstInput?.focus());
    }

    function openEdit(row) {
        editing = row;
        form = {
            field_key: row.field_key,
            selector_type: row.selector_type,
            selector_value: row.selector_value,
            options: row.options ?? null
        };
        optionsText = row.options ? JSON.stringify(row.options, null, 2) : '';
        modalOpen = true;
        queueMicrotask(() => firstInput?.focus());
    }

    function closeModal() {
        modalOpen = false;
        editing = null;
    }

    // ===== 저장 =====
    function normalizeFieldKey(s) {
        return (s || '').trim().toLowerCase();
    }
    function parseOptions(text) {
        const t = (text || '').trim();
        if (!t) return null;
        try {
            const obj = JSON.parse(t);
            if (obj && typeof obj === 'object') return obj;
            throw new Error('JSON은 객체여야 합니다.');
        } catch (e) {
            throw new Error('options JSON 파싱 실패: ' + e.message);
        }
    }

    async function save() {
        const payload = {
            field_key: normalizeFieldKey(form.field_key),
            selector_type: form.selector_type,
            selector_value: (form.selector_value || '').trim(),
            options: null
        };
        try {
            payload.options = parseOptions(optionsText); // 비어 있으면 null
        } catch (e) {
            toast.danger(e.message);
            return;
        }
        if (!payload.field_key) { toast.danger('field_key를 입력하세요.'); return; }
        if (!payload.selector_value) { toast.danger('selector_value를 입력하세요.'); return; }

        try {
            const url = editing
                ? `/channels/${channelId}/field-mappings/${editing.id}`
                : `/channels/${channelId}/field-mappings`;
            const method = editing ? 'PUT' : 'POST';
            const res = await fetchJson(url, { method, body: JSON.stringify(payload) });

            if (!res.ok) {
                const first = res.errors && Object.values(res.errors)[0]?.[0];
                toast.danger(first || res.error || '저장 실패');
                return;
            }
            toast.success(editing ? '필드 매핑을 수정했습니다.' : '필드 매핑을 등록했습니다.');
            closeModal();
            await loadList();
            await initChannel(true); // 필요시 상단 채널정보 갱신
        } catch (e) {
            toast.danger('저장 실패: ' + (e.message || String(e)));
        }
    }

    // ===== 삭제 =====
    function askRemove(row) { confirmTarget = row; confirmOpen = true; }
    async function reallyRemove() {
        if (!confirmTarget) return;
        confirmBusy = true;
        try {
            const res = await fetchJson(`/channels/${channelId}/field-mappings/${confirmTarget.id}`, { method: 'DELETE' });
            if (!res.ok) throw new Error(res.error || '삭제 실패');
            toast.success('삭제 완료');
            await loadList();
        } catch (e) {
            toast.danger('삭제 실패: ' + (e.message || String(e)));
        } finally {
            confirmBusy = false;
            confirmOpen = false;
            confirmTarget = null;
        }
    }

    // ===== 검색 이벤트 =====
    function doSearch(){ loadList(); }
    function doReset(){ q=''; loadList(); }

    // ===== expr 스니펫 =====
    const exprSnippets = [
        { label: 'CONCAT A-B', value: '`${A}-${B}`' },
        { label: 'SPLIT C "-" 앞', value: 'SPLIT(${C}, "-", 0)' },
        { label: 'SPLIT C "-" 뒤', value: 'SPLIT(${C}, "-", 1)' },
        { label: 'TRIM X', value: 'TRIM(${X})' },
        { label: 'DIGITS X', value: 'DIGITS(${X})' },
        { label: 'COALESCE X|Y', value: 'COALESCE(${X}, ${Y})' }
    ];
    function useExprSnippet(s){ form.selector_type = 'expr'; form.selector_value = s; }
</script>

<svelte:head>
    <title>필드 매핑 · 채널 {channel ? channel.name : `#${channelId}`} · OF Intranet</title>
    <meta name="description" content="채널별 엑셀 컬럼과 표준 필드의 매핑을 관리합니다." />
</svelte:head>

<section class="section" aria-labelledby="pageTitle">
    <div class="container">
        <header class="mb-4">
            <h1 id="pageTitle" class="title is-4">
                <span class="material-icons" aria-hidden="true">view_column</span>&nbsp;필드 매핑
            </h1>

            <!-- 대상 채널 표시 -->
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
                            {channel ? channel.name : `채널 ${channelId}`} · 필드 매핑
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
                <button class="button is-link" type="button" on:click={openNew}>
                    <span class="material-icons" aria-hidden="true">add_circle</span>&nbsp;새 매핑
                </button>
                <a class="button" href={`/channels/${channelId}/excel-validations`}>
                    <span class="material-icons" aria-hidden="true">rule</span>&nbsp;검증 규칙
                </a>
                <a class="button" href={`/channels/${channelId}/excel-transform`}>
                    <span class="material-icons" aria-hidden="true">sync_alt</span>&nbsp;변환 프로필
                </a>
            </div>
            <div class="level-right" style="gap:.5rem; align-items:center;">
                <SearchBar
                        bind:q
                        placeholder="field_key 또는 selector_value 검색"
                        order="input-select"
                        on:search={doSearch}
                        on:reset={doReset}
                        filterOptions={[]}
                />
                <span class="tag is-light">총 {total}개</span>
                {#if loading}<span class="tag is-info">불러오는 중…</span>{/if}
            </div>
        </div>

        <!-- 목록 -->
        <div class="table-container">
            <table class="table is-fullwidth is-hoverable is-striped">
                <caption class="is-sr-only">필드 매핑 목록</caption>
                <thead>
                <tr>
                    <th scope="col">ID</th>
                    <th scope="col">field_key</th>
                    <th scope="col">selector_type</th>
                    <th scope="col">selector_value</th>
                    <th scope="col">options</th>
                    <th scope="col" class="has-text-right">작업</th>
                </tr>
                </thead>
                <tbody>
                {#if !loading && items.length === 0}
                    <tr><td colspan="6" class="has-text-centered has-text-grey">데이터 없음</td></tr>
                {:else}
                    {#each items as it}
                        <tr>
                            <th scope="row">{it.id}</th>
                            <td><code class="mono">{it.field_key}</code></td>
                            <td><span class="tag is-light">{it.selector_type}</span></td>
                            <td class="truncate"><span title={it.selector_value}>{it.selector_value}</span></td>
                            <td class="truncate">
                                {#if it.options}
                                    <span class="tag is-info is-light" title={JSON.stringify(it.options)}>{Object.keys(it.options).join(', ') || 'options'}</span>
                                {:else}
                                    <span class="has-text-grey">—</span>
                                {/if}
                            </td>
                            <td class="has-text-right actions">
                                <div class="buttons is-small no-wrap">
                                    <button class="button is-info is-small is-light" type="button" on:click={() => openEdit(it)} title="수정" aria-label="수정">
                                        <span class="material-icons md-18" aria-hidden="true">edit</span>
                                    </button>
                                    <button class="button is-danger is-small is-light" type="button" on:click={() => askRemove(it)} title="삭제" aria-label="삭제">
                                        <span class="material-icons md-18" aria-hidden="true">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    {/each}
                {/if}
                </tbody>
            </table>
        </div>

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
        title={editing ? '필드 매핑 수정' : '필드 매핑 등록'}
        ariaDescription="표준 필드와 엑셀 열/표현식을 매핑합니다."
        width={720}
        maxHeight={720}
        draggable={true}
        on:close={closeModal}
>
    <svelte:fragment slot="body">
        <form on:submit|preventDefault={save} autocomplete="off">
            <div class="columns is-multiline">
                <div class="column is-6">
                    <div class="field">
                        <label class="label" for="fm-key">field_key</label>
                        <div class="control">
                            <input id="fm-key" class="input" type="text" bind:value={form.field_key}
                                   placeholder="예: order_no, receiver_phone"
                                   required aria-required="true" bind:this={firstInput} />
                        </div>
                        <p class="help">소문자/언더스코어 권장. 저장 시 자동 소문자화됩니다.</p>
                    </div>
                </div>

                <div class="column is-6">
                    <div class="field">
                        <label class="label" for="fm-type">selector_type</label>
                        <div class="control">
                            <div class="select is-fullwidth">
                                <select id="fm-type" bind:value={form.selector_type}>
                                    <option value="col_ref">col_ref (예: A, AA)</option>
                                    <option value="header_text">header_text (예: 상품주문번호)</option>
                                    <option value="regex">regex (예: (?i)상품주문번호)</option>
                                    <option value="expr">expr (표현식)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="column is-12">
                    <div class="field">
                        <label class="label" for="fm-value">selector_value</label>
                        <div class="control">
                            <input id="fm-value" class="input" type="text" bind:value={form.selector_value}
                                   placeholder={"예: A  |  상품주문번호  |  `` ${A}-${B} ``  |  SPLIT(${C}, \"-\", 1)"}
                                   required aria-required="true" />
                        </div>
                        {#if form.selector_type === 'expr'}
                            <div class="buttons are-small mt-2">
                                {#each exprSnippets as s}
                                    <button type="button" class="button is-light" on:click={() => useExprSnippet(s.value)}>{s.label}</button>
                                {/each}
                            </div>
                            <p class="help">
                                표현식 예: `` ${A}-${B} ``, <code>SPLIT(${C}, "-",
                                0)</code>, <code>DIGITS(${X})</code>
                            </p>
                        {/if}
                    </div>
                </div>

                <div class="column is-12">
                    <div class="field">
                        <label class="label" for="fm-options">options (JSON)</label>
                        <div class="control">
                            <textarea id="fm-options" class="textarea mono" rows="6" bind:value={optionsText}
                                      placeholder={"예: { \"delimiter\": \"-\", \"trim\": true }"}></textarea>
                        </div>
                        <p class="help">비우면 저장 시 <em>null</em>로 처리됩니다.</p>
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
        title="필드 매핑 삭제"
        message={`이 매핑을 삭제하시겠습니까?<br><code>${confirmTarget?.field_key ?? ''}</code>`}
        confirmText="영구 삭제"
        cancelText="취소"
        confirmClass="is-danger"
        busy={confirmBusy}
        draggable={true}
        on:confirm={reallyRemove}
        on:cancel={() => { confirmOpen = false; confirmTarget = null; }}
/>