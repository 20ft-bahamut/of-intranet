<script>
    import { onMount } from 'svelte';
    import { page } from '$app/stores';
    import { fetchJson } from '$lib/api/client.js';
    import ConfirmModal from '$lib/components/ConfirmModal.svelte';

    // ===== 라우트 파라미터 =====
    let productId;
    $: productId = $page.params.id;

    // ===== 상태 =====
    let product = null;       // 상단 정보(선택)
    let channels = [];        // 채널 선택용
    let items = [];           // 매핑 목록
    let loading = false;
    let error = null;
    let flash = null; // {type,text}

    // 검색/필터
    let q = '';
    let channelFilter = '';

    // 모달 & 폼
    let modalOpen = false;
    let editing = null;
    let form = {
        channel_id: '',
        listing_title: '',
        option_title: '',
        description: ''
    };
    let firstInput;

    // 삭제 확인
    let confirmOpen = false;
    let confirmTarget = null;
    let confirmBusy = false;

    function setFlash(type, text, ms=2500){
        flash = {type, text};
        setTimeout(()=> flash=null, ms);
    }

    const API = {
        product: (id) => `/products/${id}`,
        channels: () => `/channels`,
        list: (id) => `/products/${id}/product-name-mappings`,
        store: (id) => `/products/${id}/product-name-mappings`,
        update: (id, mid) => `/products/${id}/product-name-mappings/${mid}`,
        destroy: (id, mid) => `/products/${id}/product-name-mappings/${mid}`
    };

    async function loadBasics(){
        // 채널 목록
        const ch = await fetchJson(API.channels());
        channels = ch.ok ? (ch.data || []) : [];

        // 제품(옵션)
        const pr = await fetchJson(API.product(productId));
        product = pr.ok ? (pr.data || null) : null;
    }

    async function loadList(){
        loading = true; error=null;
        const res = await fetchJson(API.list(productId));
        if (!res.ok){
            error = res.error || `LOAD_FAILED(${res.status})`;
            items = [];
        } else {
            items = Array.isArray(res.data) ? res.data : (res.data?.data ?? []);
        }
        loading = false;
    }

    $: filtered = items.filter((it)=>{
        const byChannel = channelFilter ? String(it.channel_id)===String(channelFilter) : true;
        if (!q?.trim()) return byChannel;
        const s = q.toLowerCase().trim();
        return byChannel && (
            (it.listing_title||'').toLowerCase().includes(s) ||
            (it.option_title||'').toLowerCase().includes(s) ||
            (it.description||'').toLowerCase().includes(s)
        );
    });

    function openNew(){
        editing = null;
        form = { channel_id: '', listing_title: '', option_title: '', description: '' };
        modalOpen = true;
        queueMicrotask(()=> firstInput?.focus());
    }

    function openEdit(row){
        editing = row;
        form = {
            channel_id: row.channel_id ?? '',
            listing_title: row.listing_title ?? '',
            option_title: row.option_title ?? '',
            description: row.description ?? ''
        };
        modalOpen = true;
        queueMicrotask(()=> firstInput?.focus());
    }

    function closeModal(){ modalOpen=false; editing=null; }
    function onModalKeydown(e){ if(e.key==='Escape'){ e.stopPropagation(); closeModal(); } }

    function validate(p){
        const errs=[];
        if(!p.channel_id) errs.push('채널을 선택하세요.');
        if(!p.listing_title?.trim()) errs.push('채널 상품명을 입력하세요.');
        if(!p.option_title?.trim()) errs.push('옵션명을 입력하세요.');
        if(p.listing_title.length>255) errs.push('상품명은 255자 이하입니다.');
        if(p.option_title.length>255) errs.push('옵션명은 255자 이하입니다.');
        if(p.description?.length>255) errs.push('설명은 255자 이하입니다.');
        return errs;
    }

    async function save(){
        const payload={
            channel_id: Number(form.channel_id),
            listing_title: form.listing_title.trim(),
            option_title: form.option_title.trim(),
            description: form.description.trim()
        };
        const errs=validate(payload);
        if(errs.length){ setFlash('warning', errs.join(' ')); return; }

        const url = editing ? API.update(productId, editing.id) : API.store(productId);
        const method = editing ? 'PUT' : 'POST';
        const res = await fetchJson(url,{ method, body: JSON.stringify(payload)});
        if(!res.ok){
            // 409 UNIQUE 충돌 메시지 우선 처리
            if(res.status===409) setFlash('danger','중복 매핑입니다. (채널/상품명/옵션명 조합)');
            else setFlash('danger', res.error || '저장 실패');
            return;
        }
        setFlash('success', editing ? '수정 완료' : '등록 완료');
        closeModal();
        await loadList();
    }

    function askRemove(row){ confirmTarget=row; confirmOpen=true; }
    async function reallyRemove(row){
        const res = await fetchJson(API.destroy(productId,row.id),{ method:'DELETE'});
        if(!res.ok){ setFlash('danger', res.error || '삭제 실패'); return; }
        setFlash('success','삭제 완료');
        await loadList();
    }

    onMount(async()=>{
        await loadBasics();
        await loadList();
    });
</script>

<svelte:head>
    <title>채널별 제품명 매핑 · OF Intranet</title>
    <meta name="description" content="채널별 listing_title / option_title 매핑을 관리합니다." />
</svelte:head>

<section class="section" aria-labelledby="pageTitle">
    <div class="container">
        <header class="mb-4">
            <h1 id="pageTitle" class="title is-4">
                <span class="material-icons" aria-hidden="true">link</span>&nbsp;채널별 제품명 매핑
            </h1>
            {#if product}
                <p class="subtitle is-6">대상 상품: <strong>{product.name}</strong> <span class="tag is-light ml-2">code: {product.code}</span></p>
            {/if}
        </header>

        {#if flash}
            <article class="message {flash.type === 'success' ? 'is-success' : flash.type === 'danger' ? 'is-danger' : flash.type === 'warning' ? 'is-warning' : 'is-info'}" aria-live="polite">
                <div class="message-body">{flash.text}</div>
            </article>
        {/if}
        {#if error}
            <article class="message is-danger" aria-live="polite"><div class="message-body"><strong>오류:</strong> {error}</div></article>
        {/if}

        <!-- 툴바: 등록 + 필터/검색 -->
        <div class="level mb-3" aria-label="도구 모음">
            <div class="level-left">
                <button class="button is-link" type="button" on:click={openNew}>
                    <span class="material-icons" aria-hidden="true">add_circle</span>&nbsp;새 매핑 등록
                </button>
            </div>
            <div class="level-right">
                <div class="field has-addons">
                    <p class="control">
            <span class="select is-small">
              <select bind:value={channelFilter} aria-label="채널 필터">
                <option value="">전체 채널</option>
                  {#each channels as ch}
                  <option value={ch.id}>{ch.name} ({ch.code})</option>
                {/each}
              </select>
            </span>
                    </p>
                    <p class="control is-expanded">
                        <input class="input is-small" type="search" bind:value={q} placeholder="상품명/옵션명/설명 검색" aria-label="검색" />
                    </p>
                    <p class="control">
                        <button class="button is-small" type="button" on:click={()=>{ q=''; channelFilter=''; }} aria-label="초기화">
                            <span class="material-icons" aria-hidden>restart_alt</span>
                        </button>
                    </p>
                </div>
                <span class="tag is-light ml-2" aria-live="polite">총 {filtered.length}개</span>
                {#if loading}<span class="tag is-info ml-2">불러오는 중…</span>{/if}
            </div>
        </div>

        <!-- 목록 -->
        <div class="table-container" role="region" aria-label="매핑 목록 테이블">
            <table class="table is-fullwidth is-striped is-hoverable mappings-table">
                <caption class="is-sr-only">열: 채널, 채널상품명, 옵션명, 메모, 작업</caption>
                <thead>
                <tr>
                    <th scope="col">채널</th>
                    <th scope="col">채널 상품명</th>
                    <th scope="col">옵션명</th>
                    <th scope="col"></th>
                    <th scope="col">작업</th>
                </tr>
                </thead>
                <tbody>
                {#if filtered.length === 0}
                    <tr><td colspan="5" class="has-text-grey">데이터 없음</td></tr>
                {:else}
                    {#each filtered as it}
                        <tr>
                            <td>
                                {#if channels.length}
                                    {#each channels as ch}
                                        {#if ch.id === it.channel_id}
                                            <span class="tag is-light">{ch.name}</span> <small class="has-text-grey">({ch.code})</small>
                                        {/if}
                                    {/each}
                                {:else}
                                    #{it.channel_id}
                                {/if}
                            </td>
                            <td>{it.listing_title}</td>
                            <td>{it.option_title}</td>
                            <td class="actions">
                                {#if it.description}
                                    <span class="material-icons memo" aria-label="메모" data-tooltip={it.description}>sticky_note_2</span>
                                {:else}
                                    <span class="material-icons has-text-grey-light" aria-hidden="true" title="메모 없음">sticky_note_2</span>
                                {/if}
                            </td>
                            <td class="actions">
                                <div class="buttons">
                                    <button class="button is-danger is-small" type="button" on:click={()=>askRemove(it)} title="삭제" aria-label="삭제">
                                        <span class="material-icons" aria-hidden="true">delete</span>
                                    </button>
                                    <button class="button is-info is-small" type="button" on:click={()=>openEdit(it)} title="수정" aria-label="수정">
                                        <span class="material-icons" aria-hidden="true">edit</span>
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

<!-- 모달: 등록/수정 -->
{#if modalOpen}
    <div class="modal is-active" role="dialog" aria-modal="true" aria-labelledby="modalTitle" aria-describedby="modalDesc" on:keydown={onModalKeydown}>
        <div class="modal-background" on:click={closeModal}></div>
        <div class="modal-card" style="max-width:760px;">
            <header class="modal-card-head">
                <p id="modalTitle" class="modal-card-title">{editing ? '매핑 수정' : '매핑 등록'}</p>
                <button class="delete" type="button" aria-label="닫기" on:click={closeModal}></button>
            </header>
            <section class="modal-card-body">
                <p id="modalDesc" class="is-sr-only">채널, 채널상품명, 옵션명, 메모를 입력하세요.</p>
                <form on:submit|preventDefault={save} autocomplete="off">
                    <!-- Row 1: 채널 (FULL) -->
                    <div class="columns is-variable is-4">
                        <div class="column is-12">
                            <div class="field">
                                <label class="label" for="m-channel">채널</label>
                                <div class="control">
                                    <div class="select is-fullwidth">
                                        <select id="m-channel" bind:value={form.channel_id} required aria-required="true" bind:this={firstInput}>
                                            <option value="" disabled selected>채널을 선택하세요</option>
                                            {#each channels as ch}
                                                <option value={ch.id}>{ch.name} ({ch.code})</option>
                                            {/each}
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 2: 채널 상품명(6) + 옵션명(6) 동일 너비 -->
                    <div class="columns is-variable is-4">
                        <div class="column is-6">
                            <div class="field">
                                <label class="label" for="m-listing">채널 상품명</label>
                                <div class="control">
                                    <input id="m-listing" class="input" bind:value={form.listing_title} required aria-required="true" maxlength="255" placeholder="예: 사과 5kg (판매명)" />
                                </div>
                            </div>
                        </div>
                        <div class="column is-6">
                            <div class="field">
                                <label class="label" for="m-option">옵션명</label>
                                <div class="control">
                                    <input id="m-option" class="input" bind:value={form.option_title} required aria-required="true" maxlength="255" placeholder="예: 가정용 / 선물용" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 3: 관리 메모 (FULL, textarea) -->
                    <div class="columns is-variable is-4">
                        <div class="column is-12">
                            <div class="field">
                                <label class="label" for="m-desc">관리 메모</label>
                                <div class="control">
                                    <textarea id="m-desc" class="textarea" rows="4" bind:value={form.description} maxlength="255" placeholder="중복 회피 팁, 채널별 표기 주의사항 등"></textarea>
                                </div>
                                <p class="help is-flex is-justify-content-space-between"><span>선택 입력</span><span>{form.description?.length || 0}/255</span></p>
                            </div>
                        </div>
                    </div>

                    <div class="field is-grouped is-justify-content-flex-end">
                        <div class="control"><button class="button is-link" type="submit"><span class="material-icons" aria-hidden>save</span>&nbsp;저장</button></div>
                        <div class="control"><button class="button" type="button" on:click={closeModal}>취소</button></div>
                    </div>
                </form>
            </section>
            <footer class="modal-card-foot"><p class="is-size-7 has-text-grey">채널/상품명/옵션명 조합은 고유해야 합니다.</p></footer>
        </div>
    </div>
{/if}

<!-- 삭제 확인 모달 -->
<ConfirmModal
        open={confirmOpen}
        title="매핑 삭제"
        message={`선택한 매핑을 삭제하시겠습니까?`}
        confirmText="영구 삭제"
        cancelText="취소"
        confirmClass="is-danger"
        busy={confirmBusy}
        on:confirm={async ()=>{ confirmBusy=true; await reallyRemove(confirmTarget); confirmBusy=false; confirmOpen=false; confirmTarget=null; }}
        on:cancel={()=>{ confirmOpen=false; confirmTarget=null; }}
/>

<style>
    .mappings-table td, .mappings-table th { padding: .4rem .6rem; }
    td.actions { white-space: nowrap; vertical-align: middle; width: 1%; }
    td.actions .buttons { display: inline-flex; flex-wrap: nowrap; gap: .25rem; margin: 0; }
    td.actions .button.is-small { padding: .25rem .45rem; line-height: 1.1; height: auto; }
    td.actions .material-icons { font-size: 18px; line-height: 1; }

    /* 메모 툴팁 (아이콘 하단 표시) */
    .memo { position: relative; cursor: help; }
    .memo[data-tooltip]:hover::after {
        content: attr(data-tooltip);
        position: absolute; left: 50%; transform: translateX(-50%); top: 125%;
        min-width: 160px; max-width: 520px;
        background: rgba(54,54,54,.95); color: #fff; padding: .45rem .6rem; border-radius: .35rem;
        box-shadow: 0 6px 18px rgba(0,0,0,.25);
        white-space: pre-line; word-break: keep-all; text-align: left; font-size: .85rem; line-height: 1.35; z-index: 9999;
    }
    .memo[data-tooltip]:hover::before {
        content: ''; position: absolute; left: 50%; transform: translateX(-50%); top: 115%;
        border: 6px solid transparent; border-bottom-color: rgba(54,54,54,.95);
    }
    .table-container { overflow: visible; }
</style>
