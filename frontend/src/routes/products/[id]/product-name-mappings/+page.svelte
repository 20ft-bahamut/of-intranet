<script>
    import { onMount } from 'svelte';
    import { page } from '$app/stores';
    import { fetchJson, qs } from '$lib/api/client.js';
    import Modal from '$lib/components/Modal.svelte';
    import ConfirmModal from '$lib/components/ConfirmModal.svelte';
    import SearchBar from '$lib/components/SearchBar.svelte';
    import NoteBadge from '$lib/components/NoteBadge.svelte';
    import { toast } from '$lib/stores/toast.js';

    // ===== 페이지 파라미터 =====
    let productId;
    $: productId = Number(($page?.params?.id) ?? 0);

    // ===== 상단 표시용 상품/채널 =====
    let product = null; // {id,name,code,...}
    let channels = [];  // [{id,name,code}, ...]

    // ===== 목록/검색 =====
    let items = [];     // 매핑 목록
    let loading = false;
    let error = null;

    let q = '';
    let channelFilter = ''; // '' 전체

    // ===== 등록/수정 모달 =====
    let modalOpen = false;
    let editing = null;
    let firstInput;

    function emptyForm() {
        return {
            channel_id: '',
            listing_title: '',
            option_title: '',
            description: ''
        };
    }
    let form = emptyForm();

    // ===== 삭제 확인 =====
    let confirmOpen = false;
    let confirmTarget = null;
    let confirmBusy = false;

    // ===== 초기 로드 =====
    onMount(async () => {
        await Promise.all([loadProduct(), loadChannels()]);
        await load();
    });

    async function loadProduct() {
        const res = await fetchJson(`/products/${productId}`);
        if (res.ok) product = res.data;
    }

    async function loadChannels() {
        const res = await fetchJson('/channels');
        if (res.ok) channels = res.data ?? [];
    }

    async function load() {
        if (!productId) return;
        loading = true;
        error = null;
        try {
            const res = await fetchJson(
                `/products/${productId}/product-name-mappings` +
                qs({ q: q || undefined, channel_id: channelFilter || undefined })
            );
            if (!res.ok) throw new Error(res.error || '불러오기 실패');
            items = Array.isArray(res.data) ? res.data : [];
            toast.info(`매핑 ${items.length}개 불러옴`);
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
            channel_id: row.channel_id ?? '',
            listing_title: row.listing_title ?? '',
            option_title: row.option_title ?? '',
            description: row.description ?? ''
        };
        modalOpen = true;
        queueMicrotask(() => firstInput?.focus());
    }
    function closeModal() { modalOpen = false; editing = null; }

    // ===== 저장/삭제 =====
    async function save() {
        const payload = {
            channel_id: Number(form.channel_id) || '',
            listing_title: (form.listing_title || '').trim(),
            option_title: (form.option_title || '').trim(),
            description: (form.description || '').trim()
        };

        try {
            const url = editing
                ? `/products/${productId}/product-name-mappings/${editing.id}`
                : `/products/${productId}/product-name-mappings`;
            const method = editing ? 'PUT' : 'POST';
            const res = await fetchJson(url, { method, body: JSON.stringify(payload) });

            if (!res.ok) {
                const first = res.errors && Object.values(res.errors)[0]?.[0];
                toast.danger(first || res.error || '저장 실패');
                return;
            }
            toast.success(editing ? '매핑을 수정했습니다.' : '매핑을 등록했습니다.');
            closeModal();
            await load();
        } catch (e) {
            toast.danger('저장 실패: ' + (e.message || String(e)));
        }
    }

    function askRemove(row) { confirmTarget = row; confirmOpen = true; }
    async function reallyRemove(row) {
        try {
            const res = await fetchJson(
                `/products/${productId}/product-name-mappings/${row.id}`,
                { method: 'DELETE' }
            );
            if (!res.ok) throw new Error(res.error || '삭제 실패');
            toast.success('삭제 완료');
            await load();
        } catch (e) {
            toast.danger('삭제 실패: ' + (e.message || String(e)));
        }
    }

    // ===== 검색바 이벤트 =====
    function doSearch(){ load(); }
    function doReset(){ q=''; channelFilter=''; load(); }
    $: channelOptions = channels.map(c => ({ value: String(c.id), label: c.name }));

    function channelName(cid) {
        const c = channels.find(x => x.id === cid);
        return c ? c.name : `#${cid}`;
    }
</script>

<svelte:head>
    <title>상품명 매핑 · OF Intranet</title>
    <meta name="description" content="채널별 상품/옵션 이름 매핑을 관리합니다." />
</svelte:head>

<section class="section" aria-labelledby="pageTitle">
    <div class="container">
        <header class="mb-4">
            <h1 id="pageTitle" class="title is-4">
                <span class="material-icons" aria-hidden="true">rule_folder</span>&nbsp;상품명 매핑
            </h1>
            {#if product}
                <p class="subtitle is-6">
                    대상 상품: <strong>{product.name}</strong>
                    <span class="tag is-light ml-2">{product.code}</span>
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
                <a class="button" href="/products"><span class="material-icons">arrow_back</span>&nbsp;상품 목록</a>
                <button class="button is-link ml-2" type="button" on:click={openNew}>
                    <span class="material-icons">add_circle</span>&nbsp;매핑 등록
                </button>
            </div>

            <div class="level-right" style="gap:.5rem; align-items:center;">
                <SearchBar
                        bind:q
                        placeholder="채널 상품명/옵션명 검색"
                        filterOptions={channelOptions}
                        bind:filter={channelFilter}
                        order="select-input"
                        on:search={doSearch}
                        on:reset={doReset}
                />
                <span class="tag is-light" aria-live="polite">총 {items.length}개</span>
                {#if loading}<span class="tag is-info">불러오는 중…</span>{/if}
            </div>
        </div>

        <!-- 목록 -->
        <div class="table-container" role="region" aria-label="매핑 목록 테이블">
            <table class="table is-fullwidth is-striped is-hoverable mappings-table">
                <caption class="is-sr-only">
                    채널별 상품명/옵션명 매핑 표. 열: ID, 채널, 채널 상품명, 옵션명, 메모, 작업
                </caption>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>채널</th>
                    <th>채널 상품명</th>
                    <th>옵션명</th>
                    <th>메모</th>
                    <th>작업</th>
                </tr>
                </thead>
                <tbody>
                {#if items.length === 0}
                    <tr><td colspan="6" class="has-text-grey">데이터 없음</td></tr>
                {:else}
                    {#each items as it}
                        <tr>
                            <th scope="row">{it.id}</th>
                            <td>{channelName(it.channel_id)}</td>
                            <td><span class="mono">{it.listing_title}</span></td>
                            <td><span class="mono">{it.option_title || '-'}</span></td>
                            <td><NoteBadge note={it.description} title="설명" /></td>
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
        title={editing ? '매핑 수정' : '매핑 등록'}
        ariaDescription="채널/상품명/옵션명 조합은 고유해야 합니다."
        width={640}
        maxHeight={560}
        draggable={true}
        on:close={closeModal}
>
    <svelte:fragment slot="body">
        <form on:submit|preventDefault={save} autocomplete="off">
            <!-- 채널 -->
            <div class="field">
                <label class="label" for="m-channel">채널</label>
                <div class="control">
                    <div class="select is-fullwidth">
                        <select id="m-channel" bind:value={form.channel_id} required aria-required="true" bind:this={firstInput}>
                            <option value="" disabled selected>선택</option>
                            {#each channels as ch}
                                <option value={ch.id}>{ch.name}</option>
                            {/each}
                        </select>
                    </div>
                </div>
            </div>

            <!-- 채널 상품명 / 옵션명 : 같은 폭 -->
            <div class="columns is-variable is-1">
                <div class="column">
                    <div class="field">
                        <label class="label" for="m-listing">채널 상품명</label>
                        <div class="control">
                            <input id="m-listing" class="input" type="text" bind:value={form.listing_title}
                                   required aria-required="true" placeholder="예: 사과 선물세트 5kg" />
                        </div>
                    </div>
                </div>
                <div class="column">
                    <div class="field">
                        <label class="label" for="m-option">옵션명</label>
                        <div class="control">
                            <input id="m-option" class="input" type="text" bind:value={form.option_title}
                                   placeholder="예: 대과 / 옵션 없으면 비워두세요" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- 관리 메모 -->
            <div class="field">
                <label class="label" for="m-desc">관리 메모</label>
                <div class="control">
          <textarea id="m-desc" class="textarea" rows="3" bind:value={form.description}
                    placeholder="중복 회피 팁 등 내부 메모"></textarea>
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
                <button class="button" type="button" on:click={closeModal}>취소</button>
            </div>
        </div>
    </svelte:fragment>
</Modal>

<!-- 삭제 확인 (드래그 가능) -->
<ConfirmModal
        open={confirmOpen}
        title="매핑 삭제"
        message={`매핑 <strong>#${confirmTarget?.id ?? ''}</strong> 을(를) 삭제하시겠습니까?`}
        confirmText="영구 삭제"
        cancelText="취소"
        confirmClass="is-danger"
        busy={confirmBusy}
        draggable={true}
        on:confirm={async () => { confirmBusy = true; await reallyRemove(confirmTarget); confirmBusy = false; confirmOpen = false; confirmTarget = null; }}
        on:cancel={() => { confirmOpen = false; confirmTarget = null; }}
/>

<style>
    /* 컴팩트 표 + 코드글꼴 */
    .mappings-table td, .mappings-table th { padding: .4rem .6rem; }
</style>
