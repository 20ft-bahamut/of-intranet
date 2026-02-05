<script>
    import { onMount } from 'svelte';
    import { fetchJson, qs } from '$lib/api/client.js';
    import { toast } from '$lib/stores/toast.js';
    import Modal from '$lib/components/Modal.svelte';
    import NoteBadge from '$lib/components/NoteBadge.svelte';
    import { useQueryState } from '$lib/utils/queryState.js';
    import { downloadUrl, downloadBlob, timestamp } from '$lib/utils/downloads.js';

    // ── URL 쿼리 상태 (시간 필터 추가: hour_from/hour_to)
    const qsCtl = useQueryState(
        {
            q:'', channel_id:'', has_tracking:'',
            date_from:'', date_to:'',
            hour_from:'', hour_to:'',
            sort:'ordered_at', dir:'desc',
            page:1, per_page:50
        },
        { asNumbers: ['page','per_page','hour_from','hour_to'] }
    );

    // 폼 상태
    let q=''; let channelId=''; let hasTracking='';
    let dateFrom=''; let dateTo='';
    let hourFrom=''; let hourTo='';
    let sortField='ordered_at'; let sortDir='desc';
    let currentPage=1; let perPage=50;
    let loading=false;

    // 목록/채널
    let channels=[]; let channelMap=new Map();
    let rows=[]; let total=0; let lastPage=1;

    // 선택(체크박스)
    let selectedIds=new Set();
    const isSelected=(id)=>selectedIds.has(id);
    const toggleSelect=(id)=>{ selectedIds.has(id)?selectedIds.delete(id):selectedIds.add(id); selectedIds=new Set(selectedIds); };
    const clearSelection=()=>{ selectedIds=new Set(); };
    const selectAllVisible=()=>{ rows.forEach(r=>selectedIds.add(r.id)); selectedIds=new Set(selectedIds); };
    const unselectAllVisible=()=>{ rows.forEach(r=>selectedIds.delete(r.id)); selectedIds=new Set(selectedIds); };

    // 펼침 (중요: Map 변경 후 재할당해야 Svelte가 렌더링 갱신함)
    let expanded = new Map();
    const isOpen = (id, key) => !!expanded.get(id)?.[key];
    const toggleOpen = (id, key) => {
        const o = expanded.get(id) ?? {};
        o[key] = !o[key];
        expanded.set(id, o);
        expanded = new Map(expanded); // ✅ 리렌더 트리거
    };

    // "더보기"를 길 때만 노출
    const shouldMore = (s, minLen = 40) => {
        if (s === null || s === undefined) return false;
        return String(s).length > minLen;
    };

    // 메모 모달
    let memoOpen=false, memoRow=null, memoText='';
    function openMemo(row){ memoRow=row; memoText=row.admin_memo??''; memoOpen=true; setTimeout(()=>document.getElementById('memoTextarea')?.focus(),0); }
    async function saveMemo(){
        if(!memoRow) return;
        try{
            const res=await fetchJson(`/orders/${memoRow.id}`,{ method:'PATCH', body:JSON.stringify({admin_memo:memoText??null}) });
            if(res?.ok===false) throw new Error(res.message||'메모 저장 실패');
            toast.success('메모 저장 완료'); memoOpen=false; memoRow=null; memoText=''; await loadOrders();
        }catch(e){ toast.danger(e.message||String(e)); }
    }

    // ─────────────────────────────────────────────────────────────
    // 송장번호/고객요청사항 수정 모달 (세로형 최종안)
    // ─────────────────────────────────────────────────────────────
    let shipOpen = false;
    let shipRow = null;
    let shipTracking = '';
    let shipReq = '';
    let shipSaving = false;

    function openShipEdit(row){
        shipRow = row;
        shipTracking = row?.tracking_no ?? '';
        shipReq = row?.shipping_request ?? '';
        shipOpen = true;
        setTimeout(() => document.getElementById('shipTrackingInput')?.focus(), 0);
    }

    function closeShipEdit(){
        shipOpen = false;
        shipRow = null;
        shipTracking = '';
        shipReq = '';
        shipSaving = false;
    }

    async function saveShipEdit(){
        if(!shipRow) return;
        shipSaving = true;
        try{
            const res = await fetchJson(`/orders/${shipRow.id}`,{
                method:'PATCH',
                body: JSON.stringify({
                    tracking_no: (shipTracking ?? '').trim() === '' ? null : shipTracking,
                    shipping_request: (shipReq ?? '').trim() === '' ? null : shipReq,
                })
            });
            if(res?.ok === false) throw new Error(res.message || '저장 실패');
            toast.success('저장 완료');
            closeShipEdit();
            await loadOrders();
        }catch(e){
            toast.danger(e.message || String(e));
        }finally{
            shipSaving = false;
        }
    }

    // ===== 변경이력 모달/표시 =====
    let changeOpen = false;
    let changeOrder = null;
    let changeLogs = [];
    let changeLoading = false;

    // ✅ 백엔드 컬럼명 고정: has_change_logs
    const hasChanges = (r) => !!(r?.has_change_logs);
    const changeCount = (r) => (r?.change_logs_count ?? r?.change_count ?? r?.changes_count ?? null);

    const FIELD_LABEL = {
        tracking_no: '송장번호',
        shipping_request: '고객요청사항',
        receiver_name: '수취인명',
        receiver_phone: '수취인 전화',
        receiver_addr_full: '수취인 주소',
        buyer_name: '구매자명',
        buyer_phone: '구매자 전화',
        product_title: '상품명',
        option_title: '옵션명',
        quantity: '수량',
        status_std: '상태',
    };
    const prettyField = (f) => FIELD_LABEL[f] || f;

    const fmtChangedAt = (s) => (s ? String(s).replace('T',' ').slice(0,19) : '');

    async function openChanges(row){
        if(!row || !row.id){
            toast.danger('변경이력을 열 수 없습니다. (order id 없음)');
            return;
        }
        changeOpen = true;
        changeOrder = row;
        changeLogs = [];
        changeLoading = true;

        try{
            const res = await fetchJson(`/orders/${row.id}/change-logs`);
            if(!res.ok) throw new Error(res.message || '변경 이력 조회 실패');
            changeLogs = Array.isArray(res.data) ? res.data : (res.data?.data ?? []);
        }catch(e){
            toast.danger(e.message || String(e));
            changeOpen = false;
            changeOrder = null;
        }finally{
            changeLoading = false;
        }
    }

    // 시간 옵션 00~23
    const HOURS=[...Array(24).keys()]; // [0..23]

    // ── QS sync
    function syncFromQS(){
        const s=qsCtl.snapshot();
        q=s.q??''; channelId=s.channel_id??''; hasTracking=s.has_tracking??'';
        dateFrom=s.date_from??''; dateTo=s.date_to??'';
        hourFrom= (s.hour_from ?? '') === '' ? '' : Number(s.hour_from);
        hourTo  = (s.hour_to   ?? '') === '' ? '' : Number(s.hour_to);
        sortField=s.sort??'ordered_at'; sortDir=s.dir??'desc';
        currentPage=s.page??1; perPage=s.per_page??50;
    }

    function writeQS(patch={}){
        qsCtl.set({
            q, channel_id:channelId, has_tracking:hasTracking,
            date_from:dateFrom, date_to:dateTo,
            hour_from:hourFrom, hour_to:hourTo,
            sort:sortField, dir:sortDir,
            page:currentPage, per_page:perPage,
            ...patch
        });
        qsCtl.write();
    }

    // API
    async function loadChannels(){
        try{
            const r=await fetchJson('/channels');
            if(!r.ok) throw new Error(r.message||'채널 목록 실패');
            channels=(r.data||[]).filter(c=>c.is_active);
            channelMap=new Map(channels.map(c=>[String(c.id),c]));
        }catch(e){ toast.danger(e.message||String(e)); }
    }

    function params(){
        const p={ page:currentPage, per_page:perPage, sort:sortField, dir:sortDir };
        if(q.trim()) p.q=q.trim();
        if(channelId) p.channel_id=channelId;
        if(hasTracking!=='') p.has_tracking=hasTracking;
        if(dateFrom) p.date_from=dateFrom;
        if(dateTo) p.date_to=dateTo;
        if(hourFrom !== '' && hourFrom != null) p.hour_from=hourFrom;
        if(hourTo   !== '' && hourTo   != null) p.hour_to  =hourTo;
        return p;
    }

    async function loadOrders(){
        loading=true;
        try{
            const r=await fetchJson('/orders'+qs(params()));
            if(!r.ok) throw new Error(r.message||'주문 목록 실패');
            rows=r.data?.items||r.data?.data||r.data||[];

            const pg=r.data?.pagination||r.pagination||r.meta;
            if(pg){
                total=pg.total??0;
                perPage=pg.per_page??perPage;
                currentPage=pg.current_page??1;
                lastPage=pg.last_page??1;
            } else {
                total=rows.length; currentPage=1; lastPage=1;
            }
        }catch(e){ toast.danger(e.message||String(e)); }
        finally{ loading=false; }
    }

    // 이벤트
    const onSearch=()=>{ currentPage=1; writeQS(); loadOrders(); };
    const onReset =()=>{ q=''; channelId=''; hasTracking=''; dateFrom=''; dateTo='';
        hourFrom=''; hourTo=''; sortField='ordered_at'; sortDir='desc';
        currentPage=1; perPage=50; clearSelection(); writeQS(); loadOrders(); };
    const onSortChange=()=>{ currentPage=1; writeQS(); loadOrders(); };
    const changePage=(p)=>{ if(p<1||p>lastPage||p===currentPage) return; currentPage=p; writeQS(); loadOrders(); };

    const displayDate=(dt)=> dt?String(dt).slice(0,19):'';
    const orderDate=(r)=> displayDate(r.ordered_at||r.created_at||'');
    const channelLabel=(r)=>{
        if(r.channel_name && r.channel_code) return `${r.channel_name} (${r.channel_code})`;
        const c=channelMap.get(String(r.channel_id)); return c?`${c.name} (${c.code})`:`CH ${r.channel_id}`;
    };

    onMount(async()=>{ syncFromQS(); await loadChannels(); await loadOrders(); });

    // ── CSV
    function exportQuery(extra={}){
        const p=params(); delete p.page; delete p.per_page;
        return new URLSearchParams({ ...p, ...extra }).toString();
    }
    async function downloadAllCsv(){
        try{ await downloadUrl(`/orders/export?${exportQuery()}`, `orders_${timestamp()}.csv`); }
        catch(e){ toast.danger(e.message||'엑셀 다운로드 실패'); }
    }
    async function downloadNoTrackingCsv(){
        try{ await downloadUrl(`/orders/export?${exportQuery({has_tracking:'0'})}`, `orders_no_tracking_${timestamp()}.csv`); }
        catch(e){ toast.danger(e.message||'엑셀 다운로드 실패'); }
    }
    async function downloadSelectedCsv(){
        try{
            if(selectedIds.size===0){ toast.info('선택된 주문이 없습니다.'); return; }
            const base=(import.meta.env.VITE_API_BASE||'').replace(/\/+$/,'');
            const res=await fetch(base+'/orders/export/selected',{ method:'POST', headers:{'Content-Type':'application/json','Accept':'text/csv'}, credentials:'include', body:JSON.stringify({ ids:[...selectedIds] }) });
            if(!res.ok) throw new Error(`다운로드 실패(${res.status})`);
            const blob=await res.blob(); await downloadBlob(blob, `orders_selected_${timestamp()}.csv`);
        }catch(e){ toast.danger(e.message||'엑셀 다운로드 실패'); }
    }
</script>

<svelte:head><title>주문 목록 · OF Intranet</title></svelte:head>

<section class="section" aria-labelledby="pageTitle">
    <div class="container">
        <header class="mb-3">
            <h1 id="pageTitle" class="title is-4">
                <span class="material-icons" aria-hidden="true">inventory_2</span>&nbsp;주문 목록
            </h1>
            <p class="subtitle is-6">툴바 정리 · 시간(시) 필터 · 뒤로가기 상태 유지 · CSV 내보내기.</p>
        </header>

        <!-- ─────────────────────  PRETTY TOOLBAR  ───────────────────── -->
        <div class="pretty-toolbar card">
            <div class="pt-row">
                <div class="pt-title">
                    <span class="material-icons">tune</span>
                    필터
                </div>

                <div class="pt-actions">
                    <div class="btn-cluster">
                        <button class="button is-link" on:click={onSearch} disabled={loading} title="검색" type="button">
                            <span class="material-icons">search</span><span>검색</span>
                        </button>
                        <button class="button" on:click={() => { writeQS(); loadOrders(); }} disabled={loading} title="새로고침" type="button">
                            <span class="material-icons">refresh</span><span>새로고침</span>
                        </button>
                        <button class="button is-light" on:click={onReset} disabled={loading} type="button">
                            <span class="material-icons">restart_alt</span><span>초기화</span>
                        </button>
                    </div>

                    <div class="btn-cluster">
                        <button class="button is-success is-light" on:click={downloadSelectedCsv} disabled={selectedIds.size===0 || loading} title="선택된 항목 CSV" type="button">
                            <span class="material-icons">download</span><span>선택</span>
                            {#if selectedIds.size>0}<span class="tag is-success is-light ml-1">{selectedIds.size}</span>{/if}
                        </button>
                        <button class="button is-primary is-light" on:click={downloadAllCsv} disabled={loading} title="현재 조건 전체 CSV" type="button">
                            <span class="material-icons">file_download</span><span>전체</span>
                        </button>
                        <button class="button is-warning is-light" on:click={downloadNoTrackingCsv} disabled={loading} title="송장없음만 CSV" type="button">
                            <span class="material-icons">file_download</span><span>송장없음</span>
                        </button>
                    </div>

                    <span class="tag is-light total-chip">총 {total}건</span>
                </div>
            </div>

            <div class="pt-row">
                <label class="pt-field span-3">
                    <span class="pt-label">검색어</span>
                    <input class="input" type="text" placeholder="주문번호/수취인/전화/상품/옵션"
                           bind:value={q} on:keydown={(e)=> e.key==='Enter' && onSearch()} />
                </label>
            </div>

            <div class="pt-grid">
                <label class="pt-field">
                    <span class="pt-label">채널</span>
                    <div class="select is-fullwidth">
                        <select bind:value={channelId}>
                            <option value="">전체</option>
                            {#each channels as c}<option value={String(c.id)}>{c.name} ({c.code})</option>{/each}
                        </select>
                    </div>
                </label>

                <label class="pt-field">
                    <span class="pt-label">주문일(시작)</span>
                    <input class="input" type="date" bind:value={dateFrom} />
                </label>

                <label class="pt-field">
                    <span class="pt-label">주문일(시작 시각)</span>
                    <div class="select is-fullwidth">
                        <select bind:value={hourFrom}>
                            <option value="">00시</option>
                            {#each HOURS as h}<option value={h}>{String(h).padStart(2,'0')}시</option>{/each}
                        </select>
                    </div>
                </label>

                <label class="pt-field">
                    <span class="pt-label">주문일(끝)</span>
                    <input class="input" type="date" bind:value={dateTo} />
                </label>

                <label class="pt-field">
                    <span class="pt-label">주문일(끝 시각)</span>
                    <div class="select is-fullwidth">
                        <select bind:value={hourTo}>
                            <option value="">23시</option>
                            {#each HOURS as h}<option value={h}>{String(h).padStart(2,'0')}시</option>{/each}
                        </select>
                    </div>
                </label>

                <label class="pt-field">
                    <span class="pt-label">송장</span>
                    <div class="select is-fullwidth">
                        <select bind:value={hasTracking}>
                            <option value="">전체</option>
                            <option value="1">있음</option>
                            <option value="0">없음</option>
                        </select>
                    </div>
                </label>

                <label class="pt-field">
                    <span class="pt-label">정렬 필드</span>
                    <div class="select is-fullwidth">
                        <select bind:value={sortField} on:change={onSortChange}>
                            <option value="ordered_at">주문일시</option>
                            <option value="id">ID</option>
                            <option value="channel">채널명</option>
                            <option value="tracking">송장여부</option>
                            <option value="product">상품명</option>
                        </select>
                    </div>
                </label>

                <label class="pt-field">
                    <span class="pt-label">방향</span>
                    <div class="select is-fullwidth">
                        <select bind:value={sortDir} on:change={onSortChange}>
                            <option value="desc">내림차순</option>
                            <option value="asc">오름차순</option>
                        </select>
                    </div>
                </label>
            </div>
        </div>

        <div class="is-flex is-justify-content-space-between is-align-items-center mb-3 wrap-gap">
            <div class="buttons">
                <button class="button is-light" on:click={selectAllVisible} type="button">이 페이지 전체선택</button>
                <button class="button is-light" on:click={unselectAllVisible} type="button">이 페이지 선택해제</button>
                {#if selectedIds.size>0}
                    <button class="button is-white" on:click={clearSelection} type="button">전체 해제({selectedIds.size})</button>
                {/if}
            </div>
            <nav class="pagination is-small">
                <button class="pagination-previous" on:click={() => changePage(currentPage-1)} disabled={currentPage<=1} type="button">이전</button>
                <button class="pagination-next" on:click={() => changePage(currentPage+1)} disabled={currentPage>=lastPage} type="button">다음</button>
                <ul class="pagination-list"><li><span class="pagination-link is-current">{currentPage}/{lastPage}</span></li></ul>
            </nav>
        </div>

        {#if rows.length === 0}
            <div class="notification is-light">데이터가 없습니다.</div>
        {:else}
            <div class="stack">
                {#each rows as r}
                    <article class="of-card" aria-label={`주문 ${r.channel_order_no}`}>
                        <div class="of-card__head">
                            <div class="head-left">
                                <label class="selectbox"><input type="checkbox" checked={isSelected(r.id)} on:change={() => toggleSelect(r.id)} /></label>

                                <div class="chips">
                                    <span class="chip"><span class="chip__icon material-icons">tag</span>ID {r.id}</span>
                                    <span class="chip"><span class="chip__icon material-icons">storefront</span>{channelLabel(r)}</span>
                                    {#if r.status_std}<span class="chip chip--info">{r.status_std}</span>{/if}

                                    {#if hasChanges(r)}
                                        <button type="button" class="chip chip--warn chip--btn" title="변경 이력 보기" on:click={() => openChanges(r)}>
                                            <span class="chip__icon material-icons">history</span>
                                            변경이력
                                            {#if changeCount(r) != null}<span class="chip__count">({changeCount(r)})</span>{/if}
                                        </button>
                                    {/if}
                                </div>
                            </div>

                            <div class="chip chip--ghost" title="주문일(결제일)">
                                <span class="chip__icon material-icons">event</span>
                                <span class="is-size-7">주문일</span>&nbsp;<span class="mono">{orderDate(r) || '-'}</span>
                            </div>
                        </div>

                        <div class="of-card__body">
                            <div class="kv">
                                <div class="kv__icon material-icons">receipt_long</div>
                                <div class="kv__label">주문번호</div>
                                <div class="kv__value mono">
                                    <div class:clamp-1={!isOpen(r.id,'orderno')}>{r.channel_order_no}</div>
                                    {#if shouldMore(r.channel_order_no, 28)}
                                        <button type="button" class="btn-more" on:click={()=>toggleOpen(r.id,'orderno')}>
                                            {isOpen(r.id,'orderno')?'접기':'더보기'}
                                        </button>
                                    {/if}
                                </div>
                            </div>

                            <div class="kv">
                                <div class="kv__icon material-icons">inventory_2</div>
                                <div class="kv__label">상품/옵션 · 수량</div>
                                <div class="kv__value">
                                    <div class:clamp-2={!isOpen(r.id,'product')}>
                                        <strong>{r.product_title || '-'}</strong>{#if r.option_title}<span> · {r.option_title}</span>{/if}
                                        <span class="tag is-info is-light ml-1">x{r.quantity}</span>
                                    </div>
                                    <div class="text-dim is-size-7">표준코드: <code class="mono">{r.product_code || (r.product && r.product.code) || '-'}</code></div>
                                    {#if shouldMore((r.product_title||'') + ' ' + (r.option_title||''), 50)}
                                        <button type="button" class="btn-more" on:click={()=>toggleOpen(r.id,'product')}>
                                            {isOpen(r.id,'product')?'접기':'더보기'}
                                        </button>
                                    {/if}
                                </div>
                            </div>

                            <div class="kv"><div class="kv__icon material-icons">local_shipping</div><div class="kv__label">송장번호</div><div class="kv__value mono">{r.tracking_no || '-'}</div></div>

                            <div class="kv">
                                <div class="kv__icon material-icons">chat</div>
                                <div class="kv__label">고객요청사항</div>
                                <div class="kv__value">
                                    <div class:clamp-2={!isOpen(r.id,'shipreq')}>{r.shipping_request || '-'}</div>
                                    {#if shouldMore(r.shipping_request, 30)}
                                        <button type="button" class="btn-more" on:click={()=>toggleOpen(r.id,'shipreq')}>
                                            {isOpen(r.id,'shipreq')?'접기':'더보기'}
                                        </button>
                                    {/if}
                                </div>
                            </div>

                            <div class="kv"><div class="kv__icon material-icons">person</div><div class="kv__label">구매자</div><div class="kv__value">{r.buyer_name || '-'} · <span class="mono">{r.buyer_phone || '-'}</span></div></div>
                            <div class="kv"><div class="kv__icon material-icons">badge</div><div class="kv__label">수취인/전화</div><div class="kv__value"><strong>{r.receiver_name}</strong> · <span class="mono">{r.receiver_phone}</span></div></div>
                            <div class="kv"><div class="kv__icon material-icons">markunread_mailbox</div><div class="kv__label">우편번호</div><div class="kv__value mono">{r.receiver_postcode}</div></div>

                            <div class="kv">
                                <div class="kv__icon material-icons">home</div>
                                <div class="kv__label">주소</div>
                                <div class="kv__value">
                                    <div class:clamp-2={!isOpen(r.id,'addr')}>{r.receiver_addr_full}</div>
                                    {#if shouldMore(r.receiver_addr_full, 40)}
                                        <button type="button" class="btn-more" on:click={()=>toggleOpen(r.id,'addr')}>
                                            {isOpen(r.id,'addr')?'접기':'더보기'}
                                        </button>
                                    {/if}
                                </div>
                            </div>
                        </div>

                        <div class="of-card__foot">
                            <div class="foot-left">
                                <NoteBadge note={r.admin_memo} title="관리자 메모" />
                                <button class="button is-light is-small icon-btn" title="메모 편집" on:click={()=>openMemo(r)} type="button">
                                    <span class="material-icons">edit_note</span>
                                </button>

                                <button class="button is-light is-small icon-btn" title="송장/요청사항 수정" on:click={()=>openShipEdit(r)} type="button">
                                    <span class="material-icons">edit</span>
                                </button>
                            </div>
                            <div class="foot-right">
                                <button class="button is-light is-small icon-btn" title="새로고침" on:click={() => { writeQS(); loadOrders(); }} type="button">
                                    <span class="material-icons">refresh</span>
                                </button>
                                <span class="text-dim is-size-7 ml-2">업데이트 <span class="mono">{displayDate(r.updated_at)}</span></span>
                            </div>
                        </div>
                    </article>
                {/each}
            </div>
        {/if}

        <div class="is-flex is-justify-content-space-between is-align-items-center mt-4 wrap-gap">
            <span class="tag is-light">총 {total}건</span>
            <nav class="pagination is-small">
                <button class="pagination-previous" on:click={() => changePage(currentPage-1)} disabled={currentPage<=1} type="button">이전</button>
                <button class="pagination-next" on:click={() => changePage(currentPage+1)} disabled={currentPage>=lastPage} type="button">다음</button>
                <ul class="pagination-list"><li><span class="pagination-link is-current">{currentPage}/{lastPage}</span></li></ul>
            </nav>
        </div>

        <!-- 메모 모달 -->
        <Modal bind:open={memoOpen} on:close={() => { memoOpen=false; memoRow=null; memoText=''; }}
               title="관리자 메모" ariaDescription="주문 관리자 메모 편집" width={640}>
            <svelte:fragment slot="body">
                <div class="field">
                    <label class="label">주문번호</label>
                    <div class="control"><code class="mono">{memoRow?.channel_order_no}</code></div>
                </div>
                <div class="field">
                    <label class="label" for="memoTextarea">메모</label>
                    <textarea id="memoTextarea" class="textarea" rows="6" bind:value={memoText}
                              placeholder="이 주문에 대한 관리 메모를 입력하세요."></textarea>
                </div>
            </svelte:fragment>
            <svelte:fragment slot="footer">
                <div class="buttons">
                    <button class="button is-link" on:click={saveMemo} type="button"><span class="material-icons" aria-hidden="true">save</span>&nbsp;저장</button>
                    <button class="button" on:click={() => { memoOpen=false; memoRow=null; memoText=''; }} type="button">닫기</button>
                </div>
            </svelte:fragment>
        </Modal>

        <!-- 송장/고객요청사항 수정 모달 (세로형) -->
        <Modal
                bind:open={shipOpen}
                on:close={closeShipEdit}
                title="송장번호 / 고객요청사항"
                ariaDescription="송장번호와 고객요청사항을 수정합니다"
                width={760}
        >
            <svelte:fragment slot="body">
                <div class="shipv">
                    <div class="shipv-head">
                        <div>
                            <div class="shipv-label">주문번호</div>
                            <div class="shipv-orderno mono">{shipRow?.channel_order_no}</div>
                        </div>
                        <div class="shipv-hint">
                            <span class="tag is-light">편집 전용</span>
                            <span class="text-dim">빈값 저장 시 null 처리</span>
                        </div>
                    </div>

                    <section class="shipv-card" aria-label="송장번호 입력">
                        <div class="shipv-card__title">
                            <span class="material-icons" aria-hidden="true">local_shipping</span>
                            송장번호
                        </div>

                        <div class="field">
                            <div class="control">
                                <input
                                        id="shipTrackingInput"
                                        class="input shipv-input"
                                        type="text"
                                        placeholder="송장번호 입력 (Enter로 저장)"
                                        bind:value={shipTracking}
                                        on:keydown={(e)=>{ if(e.key==='Enter'){ e.preventDefault(); saveShipEdit(); } }}
                                />
                            </div>
                            <p class="help shipv-help">숫자/문자 모두 허용. Enter로 바로 저장 가능합니다.</p>
                        </div>

                        <div class="shipv-preview">
                            <div class="shipv-preview__label">현재 값</div>
                            <div class="shipv-preview__box mono">{shipRow?.tracking_no || '-'}</div>
                        </div>
                    </section>

                    <section class="shipv-card" aria-label="고객요청사항 입력">
                        <div class="shipv-card__title">
                            <span class="material-icons" aria-hidden="true">chat</span>
                            고객요청사항
                        </div>

                        <div class="field">
                            <div class="control">
                                <textarea
                                        class="textarea shipv-textarea"
                                        rows="6"
                                        placeholder="고객요청사항 입력"
                                        bind:value={shipReq}
                                ></textarea>
                            </div>
                            <p class="help shipv-help">농협몰에서 'null' 텍스트가 오는 경우가 있어 그대로 저장될 수 있습니다.</p>
                        </div>

                        <div class="shipv-preview">
                            <div class="shipv-preview__label">현재 값</div>
                            <div class="shipv-preview__box">{shipRow?.shipping_request || '-'}</div>
                        </div>
                    </section>

                    <div class="shipv-note">
                        저장은 주문 데이터만 갱신합니다. 변경 이력은 <b>변경이력</b>에서 확인합니다.
                    </div>
                </div>
            </svelte:fragment>

            <svelte:fragment slot="footer">
                <div class="shipv-footer">
                    <button
                            class="button is-light"
                            disabled={!shipRow}
                            type="button"
                            on:click={() => {
                              const row = shipRow;           // ✅ 먼저 캡처
                              if (!row) return;
                              closeShipEdit();               // 모달 닫고 상태 초기화
                              openChanges(row);              // ✅ 캡처한 row로 호출
                            }}

                    >
                        <span class="material-icons" aria-hidden="true">history</span>&nbsp;변경이력
                    </button>

                    <div class="shipv-footer__right">
                        <button class="button is-link" on:click={saveShipEdit} disabled={shipSaving} type="button">
                            <span class="material-icons" aria-hidden="true">save</span>&nbsp;저장
                        </button>
                        <button class="button" on:click={closeShipEdit} disabled={shipSaving} type="button">닫기</button>
                    </div>
                </div>
            </svelte:fragment>
        </Modal>

        <!-- 변경이력 모달 -->
        <Modal
                bind:open={changeOpen}
                on:close={() => { changeOpen=false; changeOrder=null; changeLogs=[]; }}
                title="변경 이력"
                ariaDescription="주문 변경 이력 diff"
                width={900}
        >
            <svelte:fragment slot="body">
                <div class="mb-3">
                    <div class="is-size-7 text-dim">주문번호</div>
                    <div class="mono has-text-weight-semibold">{changeOrder?.channel_order_no}</div>
                </div>

                {#if changeLoading}
                    <div class="notification is-light">불러오는 중…</div>
                {:else if changeLogs.length === 0}
                    <div class="notification is-light">변경 이력이 없습니다.</div>
                {:else}
                    <div class="diff-list">
                        {#each changeLogs as log}
                            <div class="diff-item">
                                <div class="diff-head">
                                    <span class="diff-field">{prettyField(log.field)}</span>
                                    <span class="diff-meta">
                                        <span class="tag is-light">{log.source || '-'}</span>
                                        <span class="mono">{fmtChangedAt(log.created_at)}</span>
                                    </span>
                                </div>

                                <div class="diff-body">
                                    <div class="diff-col">
                                        <div class="diff-label">이전</div>
                                        <div class="diff-box diff-old mono">{log.old_value ?? ''}</div>
                                    </div>
                                    <div class="diff-arrow material-icons" aria-hidden="true">arrow_forward</div>
                                    <div class="diff-col">
                                        <div class="diff-label">변경</div>
                                        <div class="diff-box diff-new mono">{log.new_value ?? ''}</div>
                                    </div>
                                </div>
                            </div>
                        {/each}
                    </div>
                {/if}
            </svelte:fragment>

            <svelte:fragment slot="footer">
                <div class="buttons">
                    <button class="button" on:click={() => { changeOpen=false; changeOrder=null; changeLogs=[]; }} type="button">닫기</button>
                </div>
            </svelte:fragment>
        </Modal>

    </div>
</section>

<style>
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "D2Coding", "Noto Sans Mono CJK", monospace; }
    .text-dim { color:#6b7280; }
    .wrap-gap { gap:.5rem; flex-wrap:wrap; }

    /* ===== 카드 ===== */
    .stack { display:flex; flex-direction:column; gap:1rem; }
    .of-card { border:1px solid #eff1f5; border-radius:14px; background:#fff; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.04); }
    .of-card__head, .of-card__foot { display:flex; align-items:center; justify-content:space-between; padding:.75rem 1rem; background:linear-gradient(180deg,#fafbfd,#f7f8fb); }
    .of-card__body { padding:.5rem 1rem .75rem; display:grid; grid-template-columns:1fr; gap:.4rem; }
    .head-left { display:flex; align-items:center; gap:.5rem; }

    /* 칩 */
    .chips { display:flex; gap:.5rem; align-items:center; flex-wrap:wrap; }
    .chip { display:inline-flex; align-items:center; gap:.3rem; padding:.25rem .6rem; border-radius:999px; background:#eef2ff; color:#3949ab; font-weight:600; font-size:.8rem; }
    .chip__icon { font-size:18px; }
    .chip--ghost { background:#f1f5f9; color:#334155; }
    .chip--info { background:#e6fffb; color:#0b8f7a; }

    /* 변경이력 칩 */
    .chip--warn { background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; }
    .chip--btn { border:none; cursor:pointer; }
    .chip--btn:hover { filter:brightness(.98); }
    .chip__count { margin-left:.2rem; font-weight:800; }

    /* key-value rows */
    .kv { display:grid; grid-template-columns:20px 140px 1fr; align-items:flex-start; gap:.75rem; padding:.35rem 0; border-bottom:1px dashed #eef2f6; }
    .kv:last-child { border-bottom:none; }
    .kv__icon { font-size:18px; color:#94a3b8; text-align:center; padding-top:.2rem; }
    .kv__label { color:#6b7280; font-weight:600; }
    .kv__value { min-width:0; }

    /* 줄임/버튼 */
    .clamp-1, .clamp-2 { display:-webkit-box; -webkit-box-orient: vertical; overflow: hidden; }
    .clamp-1 { -webkit-line-clamp:1; }
    .clamp-2 { -webkit-line-clamp:2; }
    .btn-more { margin-left:.25rem; padding:0 .25rem; border:none; background:none; color:#4f46e5; font-size:.8rem; }
    .btn-more:hover { text-decoration: underline; }

    .icon-btn { padding:0 8px; height:32px; display:inline-flex; align-items:center; justify-content:center; }
    .icon-btn .material-icons { font-size:22px; }
    .selectbox input { width:16px; height:16px; }

    /* ===== PRETTY TOOLBAR ===== */
    .card.pretty-toolbar { border:1px solid #eef2f7; border-radius:14px; padding:14px 16px; background:#fff; }
    .pretty-toolbar { position: sticky; top: 0; z-index: 20; box-shadow: 0 8px 16px -16px rgba(0,0,0,.15); }
    .pt-row { display:flex; align-items:center; justify-content:space-between; gap:.75rem; flex-wrap:wrap; margin-bottom:.75rem; }
    .pt-title { display:flex; align-items:center; gap:.4rem; font-weight:700; color:#334155; }
    .pt-title .material-icons { font-size:20px; color:#64748b; }
    .pt-actions { display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; }
    .btn-cluster { display:flex; gap:.4rem; flex-wrap:wrap; }
    .total-chip { margin-left:.25rem; }
    .pt-grid { display:grid; grid-template-columns: repeat(8, minmax(120px, 1fr)); gap:.75rem; }
    .pt-field { display:flex; flex-direction:column; gap:.35rem; }
    .pt-field .pt-label { font-size:.8rem; color:#6b7280; font-weight:600; }
    .span-3 { grid-column: span 3; }
    @media (max-width: 1100px) { .pt-grid { grid-template-columns: repeat(4, minmax(120px, 1fr)); } .span-3 { grid-column: 1 / -1; } }
    @media (max-width: 640px) { .pt-grid { grid-template-columns: repeat(2, minmax(120px, 1fr)); } }

    /* ===== diff UI ===== */
    .diff-list { display:flex; flex-direction:column; gap:.75rem; }
    .diff-item { border:1px solid #eef2f7; border-radius:12px; padding:.75rem; background:#fff; }
    .diff-head { display:flex; justify-content:space-between; align-items:center; gap:.5rem; margin-bottom:.5rem; }
    .diff-field { font-weight:800; color:#334155; }
    .diff-meta { display:flex; align-items:center; gap:.5rem; color:#64748b; font-size:.85rem; }
    .diff-body { display:grid; grid-template-columns:1fr 32px 1fr; gap:.6rem; align-items:stretch; }
    .diff-col { min-width:0; }
    .diff-label { font-size:.75rem; color:#6b7280; font-weight:700; margin-bottom:.25rem; }
    .diff-box { border-radius:10px; padding:.6rem .65rem; border:1px solid #e5e7eb; white-space:pre-wrap; word-break:break-word; }
    .diff-old { background:#f8fafc; color:#334155; }
    .diff-new { background:#ecfeff; color:#0f766e; border-color:#a5f3fc; }
    .diff-arrow { display:flex; align-items:center; justify-content:center; color:#94a3b8; }

    /* ─────────────────────────────────────────────────────────────
       세로형 송장/요청사항 편집 모달 스타일
       ───────────────────────────────────────────────────────────── */
    .shipv{
        max-width: 100%;
        overflow-x: hidden; /* ✅ 가로 스크롤 방어 */
    }

    .shipv-head{
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap:1rem;
        padding:.25rem 0 .85rem;
        border-bottom:1px solid #eef2f7;
        margin-bottom: 1rem;
        flex-wrap:wrap;
    }
    .shipv-label{
        font-size:.75rem;
        color:#6b7280;
        font-weight:800;
        margin-bottom:.15rem;
    }
    .shipv-orderno{
        font-size:1.05rem;
        font-weight:900;
        color:#111827;
    }
    .shipv-hint{
        display:flex;
        align-items:center;
        gap:.6rem;
        flex-wrap:wrap;
        justify-content:flex-end;
    }

    .shipv-card{
        border:1px solid #eef2f7;
        border-radius:14px;
        padding:14px;
        background:#fff;
        box-shadow: 0 1px 4px rgba(0,0,0,.04);
        margin-bottom: 12px;
    }
    .shipv-card__title{
        display:flex;
        align-items:center;
        gap:.45rem;
        font-weight:900;
        color:#334155;
        margin-bottom:.75rem;
    }
    .shipv-card__title .material-icons{
        font-size:20px;
        color:#64748b;
    }

    .shipv-input{
        height: 44px;
        font-size: 1.05rem;
        font-weight: 800;
        letter-spacing: .2px;
    }
    .shipv-textarea{
        font-size: .98rem;
        line-height: 1.45;
    }
    .shipv-help{
        margin-top:.45rem;
        color:#6b7280;
    }

    .shipv-preview{
        margin-top:.9rem;
        padding-top:.85rem;
        border-top:1px dashed #eef2f6;
    }
    .shipv-preview__label{
        font-size:.75rem;
        color:#6b7280;
        font-weight:800;
        margin-bottom:.35rem;
    }
    .shipv-preview__box{
        background:#f8fafc;
        border:1px solid #e5e7eb;
        border-radius:12px;
        padding:.6rem .7rem;
        word-break: break-word;
        max-width:100%;
        box-sizing:border-box;
    }

    .shipv-note{
        margin-top: 8px;
        padding: 10px 12px;
        border-radius: 12px;
        background:#f1f5f9;
        color:#475569;
        font-size:.9rem;
    }

    .shipv-footer{
        width:100%;
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:.75rem;
        flex-wrap:wrap;
    }
    .shipv-footer__right{
        display:flex;
        gap:.5rem;
        flex-wrap:wrap;
    }
</style>
