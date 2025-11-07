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

    let channelId; $: channelId = Number($page.params.id);

    // ------- 채널 메타 -------
    let channel = null;
    let channelLoading = false;
    async function initChannel(force = false) {
        channelLoading = true;
        channel = force ? await refreshChannelMeta(channelId) : await loadChannelMeta(channelId);
        channelLoading = false;
    }

    // ------- 목록 상태 -------
    let items = [];
    let loading = false;
    let error = null;
    let total = 0;

    let q = '';

    // ------- 필드 메타 (라벨/타입/그룹) -------
    const fieldGroups = [
        {
            label: '핵심 식별/상품',
            keys: [
                { name:'channel_order_no', type:'varchar(100)', desc:'채널 주문번호' },
                { name:'product_id', type:'bigint', desc:'상품 ID(FK)' },
                { name:'product_title', type:'varchar(255)', desc:'상품명' },
                { name:'option_title', type:'varchar(255)', desc:'옵션명' },
                { name:'quantity', type:'int', desc:'수량' },
                { name:'tracking_no', type:'varchar(64)', desc:'송장번호' },
                { name:'ordered_at', type:'datetime', desc:'결제/주문 일시' },
                { name:'status_std', type:'varchar(50)', desc:'표준 상태' },
            ]
        },
        {
            label: '주문자',
            keys: [
                { name:'buyer_name', type:'varchar(100)', desc:'주문자 이름' },
                { name:'buyer_phone', type:'varchar(50)', desc:'주문자 전화' },
                { name:'buyer_postcode', type:'varchar(10)', desc:'주문자 우편번호' },
                { name:'buyer_addr_full', type:'varchar(400)', desc:'주문자 전체주소' },
                { name:'buyer_addr1', type:'varchar(255)', desc:'주문자 기본주소' },
                { name:'buyer_addr2', type:'varchar(255)', desc:'주문자 상세주소' },
            ]
        },
        {
            label: '수취인/배송',
            keys: [
                { name:'receiver_name', type:'varchar(100)', desc:'수취인 이름' },
                { name:'receiver_phone', type:'varchar(50)', desc:'수취인 전화' },
                { name:'receiver_postcode', type:'varchar(10)', desc:'수취인 우편번호' },
                { name:'receiver_addr_full', type:'varchar(400)', desc:'수취인 전체주소' },
                { name:'receiver_addr1', type:'varchar(255)', desc:'수취인 기본주소' },
                { name:'receiver_addr2', type:'varchar(255)', desc:'수취인 상세주소' },
                { name:'shipping_request', type:'varchar(255)', desc:'배송요청사항' },
            ]
        },
        {
            label: '메모/원천',
            keys: [
                { name:'customer_note', type:'varchar(255)', desc:'고객 메모' },
                { name:'admin_memo', type:'varchar(255)', desc:'관리자 메모' },
                { name:'status_src', type:'varchar(100)', desc:'원천 상태' },
            ]
        },
    ];

    const flatFieldOptions = fieldGroups.flatMap(g =>
        g.keys.map(k => ({ ...k, group: g.label }))
    );
    const fieldMetaMap = Object.fromEntries(
        flatFieldOptions.map(k => [k.name, k])
    );
    function labelOf(key) {
        const m = fieldMetaMap[key];
        return m ? m.desc : key;
    }
    function typeOf(key) {
        const m = fieldMetaMap[key];
        return m ? m.type : '';
    }
    function groupOf(key) {
        const m = fieldMetaMap[key];
        return m ? m.group : '';
    }

    // ------- 모달 상태 -------
    let modalOpen = false;
    let editing = null;
    let form = emptyForm();
    let optionsText = '';
    let firstInput;

    function emptyForm() {
        return {
            field_key: '',
            selector_type: 'col_ref', // col_ref|header_text|regex|expr
            selector_value: '',
            options: null
        };
    }

    // ------- 삭제 -------
    let confirmOpen = false;
    let confirmTarget = null;
    let confirmBusy = false;

    // ------- 데이터 로드 -------
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

    onMount(() => { initChannel(); loadList(); });

    // 이미 사용한 key 집합
    $: usedKeys = new Set(items.map(it => it.field_key));

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
    function closeModal(){ modalOpen = false; editing = null; }

    function parseOptions(text) {
        const t = (text || '').trim();
        if (!t) return null;
        try {
            const obj = JSON.parse(t);
            if (obj && typeof obj === 'object') return obj;
            throw new Error('options JSON은 객체여야 합니다.');
        } catch (e) { throw new Error('options JSON 파싱 실패: ' + e.message); }
    }

    async function save() {
        const payload = {
            field_key: (form.field_key || '').trim().toLowerCase(),
            selector_type: form.selector_type,
            selector_value: (form.selector_value || '').trim(),
            options: null
        };
        try { payload.options = parseOptions(optionsText); } catch (e) { toast.danger(e.message); return; }
        if (!payload.field_key) { toast.danger('field_key를 선택하세요.'); return; }
        if (!payload.selector_value) { toast.danger('selector_value를 입력하세요.'); return; }

        // 프론트 중복 방지: 새로 만들 때, 이미 사용 중이면 막기
        if (!editing && usedKeys.has(payload.field_key)) {
            toast.danger(`이미 사용 중인 필드입니다: ${payload.field_key}`);
            return;
        }

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
            await initChannel(true);
        } catch (e) {
            toast.danger('저장 실패: ' + (e.message || String(e)));
        }
    }

    function askRemove(row){ confirmTarget = row; confirmOpen = true; }
    async function reallyRemove() {
        if (!confirmTarget) return;
        confirmBusy = true;
        try {
            const res = await fetchJson(`/channels/${channelId}/field-mappings/${confirmTarget.id}`, { method:'DELETE' });
            if (!res.ok) throw new Error(res.error || '삭제 실패');
            toast.success('삭제 완료');
            await loadList();
        } catch (e) {
            toast.danger('삭제 실패: ' + (e.message || String(e)));
        } finally {
            confirmBusy = false; confirmOpen = false; confirmTarget = null;
        }
    }

    function doSearch(){ loadList(); }
    function doReset(){ q=''; loadList(); }

    const exprSnippets = [
        { label: 'CONCAT A-B', value: '`${A}-${B}`' },
        { label: 'SPLIT C "-" 앞', value: 'SPLIT(${C}, "-", 0)' },
        { label: 'SPLIT C "-" 뒤', value: 'SPLIT(${C}, "-", 1)' },
        { label: 'TRIM X', value: 'TRIM(${X})' },
        { label: 'DIGITS X', value: 'DIGITS(${X})' },
        { label: 'COALESCE X|Y', value: 'COALESCE(${X}, ${Y})' },
    ];
    function useExprSnippet(s){ form.selector_type='expr'; form.selector_value=s; }
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
                        <a href={`/channels/${channelId}/field-mappings`} aria-current="page">
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
                <SearchBar bind:q placeholder="field_key 또는 selector_value 검색"
                           order="input-select" on:search={doSearch} on:reset={doReset} filterOptions={[]} />
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
                    <th>ID</th>
                    <th>field_key</th>
                    <th>selector_type</th>
                    <th>selector_value</th>
                    <th>options</th>
                    <th class="has-text-right">작업</th>
                </tr>
                </thead>
                <tbody>
                {#if !loading && items.length === 0}
                    <tr><td colspan="6" class="has-text-centered has-text-grey">데이터 없음</td></tr>
                {:else}
                    {#each items as it}
                        <tr>
                            <th scope="row">{it.id}</th>
                            <td>
                                <strong>{labelOf(it.field_key)}</strong>
                                <span class="tag is-light ml-2">{typeOf(it.field_key) || '-'}</span>
                                <code class="mono ml-2">{it.field_key}</code>
                                {#if groupOf(it.field_key)}
                                    <span class="tag is-info is-light ml-1">{groupOf(it.field_key)}</span>
                                {/if}
                            </td>
                            <td><span class="tag is-light">{it.selector_type}</span></td>
                            <td class="truncate"><span title={it.selector_value}>{it.selector_value}</span></td>
                            <td class="truncate">
                                {#if it.options}
                  <span class="tag is-info is-light" title={JSON.stringify(it.options)}>
                    {Object.keys(it.options).join(', ') || 'options'}
                  </span>
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
        width={760} maxHeight={760} draggable={true}
        on:close={closeModal}
>
    <svelte:fragment slot="body">
        <form on:submit|preventDefault={save} autocomplete="off">
            <div class="columns is-multiline">
                <!-- field_key: 그룹/검색 용이하게 optgroup + 사용중 비활성화 -->
                <div class="column is-6">
                    <div class="field">
                        <label class="label" for="fm-key">field_key</label>
                        <div class="control">
                            <div class="select is-fullwidth">
                                <select id="fm-key" bind:value={form.field_key} required aria-required="true" bind:this={firstInput}>
                                    <option value="" disabled>필드를 선택하세요</option>
                                    {#each fieldGroups as g}
                                        <optgroup label={g.label}>
                                            {#each g.keys as k}
                                                <option
                                                        value={k.name}
                                                        disabled={ usedKeys.has(k.name) && (!editing || editing.field_key !== k.name) }
                                                >
                                                    {k.name} — {k.type} ({k.desc}){usedKeys.has(k.name) && (!editing || editing.field_key !== k.name) ? ' · 사용중' : ''}
                                                </option>
                                            {/each}
                                        </optgroup>
                                    {/each}
                                </select>
                            </div>
                        </div>
                        <p class="help">
                            이미 매핑된 필드는 <em>사용중</em>으로 표시되어 선택할 수 없습니다.
                        </p>
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
                            <input id="fm-value" class="input" type="text"
                                   bind:value={form.selector_value}
                                   placeholder={'예: A  |  상품주문번호  |  `${A}-${B}`  |  SPLIT(${C}, "-", 1)'}
                                   required aria-required="true" />
                        </div>
                        {#if form.selector_type === 'expr'}
                            <div class="buttons are-small mt-2">
                                {#each exprSnippets as s}
                                    <button type="button" class="button is-light" on:click={() => useExprSnippet(s.value)}>{s.label}</button>
                                {/each}
                            </div>
                            <p class="help">
                                표현식 예: <code>{'`${A}-${B}`'}</code>, <code>{'SPLIT(${C}, "-", 0)'}</code>, <code>{'DIGITS(${X})'}</code>
                            </p>
                        {/if}
                    </div>
                </div>

                <div class="column is-12">
                    <div class="field">
                        <label class="label" for="fm-options">options (JSON)</label>
                        <div class="control">
              <textarea id="fm-options" class="textarea mono" rows="6"
                        bind:value={optionsText}
                        placeholder={'예: { "delimiter": "-", "trim": true }'}></textarea>
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

<ConfirmModal
        open={confirmOpen}
        title="필드 매핑 삭제"
        message={`이 매핑을 삭제하시겠습니까?<br><code>${confirmTarget ? confirmTarget.field_key : ''}</code>`}
        confirmText="영구 삭제" cancelText="취소" confirmClass="is-danger"
        busy={confirmBusy} draggable={true}
        on:confirm={reallyRemove}
        on:cancel={() => { confirmOpen = false; confirmTarget = null; }}
/>