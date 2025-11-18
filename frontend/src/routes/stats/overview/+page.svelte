<script>
    import { onMount } from 'svelte';
    import { fetchJson, qs } from '$lib/api/client.js';
    import { useQueryState } from '$lib/utils/queryState.js';

    // 날짜 유틸
    const toLocalDate = (d) => new Date(d.getTime() - d.getTimezoneOffset()*60000).toISOString().slice(0,10);
    const today = toLocalDate(new Date());
    const day = (n) => toLocalDate(new Date(Date.now() + n*86400000));

    // URL 동기화
    const qsCtl = useQueryState({ from: day(-6), to: today, channel:'', q:'', limit: 10 }, { asNumbers: ['limit'] });
    let from = day(-6), to = today, channel = '', q = '', limit = 10;

    // 데이터
    let channels = [];
    let loading = false, error = null;
    let kpi = { yesterday_orders:0, today_orders:0, week_orders:0, channels_total:0 };
    let topProducts = [];
    let byChannel = [];
    let recentOrders = [];

    const baseParams = () => {
        const p = { from, to };
        if (channel) p.channel = channel;
        if (q.trim()) p.q = q.trim();
        return p;
    };

    function syncFromQS(){ const s=qsCtl.snapshot(); from=s.from??from; to=s.to??to; channel=s.channel??''; q=s.q??''; limit=Number(s.limit??10)||10; }
    function writeQS(p={}){ qsCtl.set({from,to,channel,q,limit,...p}); qsCtl.write(); }

    async function loadChannels(){
        try{ const r = await fetchJson('/channels'); if(r.ok) channels = (r.data||[]).filter(c=>c.is_active); }catch{}
    }

    async function fetchAll(){
        loading = true; error = null;
        try{
            const ov = await fetchJson('/stats/overview'+qs({ date: to }));
            const tp = await fetchJson('/stats/top-products'+qs({ ...baseParams(), limit }));
            const bc = await fetchJson('/stats/by-channel'+qs(baseParams()));
            const ro = await fetchJson('/stats/recent-orders'+qs({ limit: 10 }));

            if(!ov.ok) throw new Error(ov.message||'overview 실패');
            if(!tp.ok) throw new Error(tp.message||'top-products 실패');
            if(!bc.ok) throw new Error(bc.message||'by-channel 실패');
            if(!ro.ok) throw new Error(ro.message||'recent-orders 실패');

            kpi = ov.data ?? kpi;
            topProducts = tp.data ?? [];
            byChannel = bc.data ?? [];
            recentOrders = ro.data ?? [];
        }catch(e){ error = String(e.message||e); }
        finally{ loading=false; }
    }

    function quick(t){ const m={today:[today,today],yesterday:[day(-1),day(-1)],week:[day(-6),today],mon:[day(-29),today]}; [from,to]=m[t]; submit(); }
    function submit(){ writeQS(); fetchAll(); }
    function reset(){ channel=''; q=''; quick('week'); }

    const pct = v => (typeof v==='number' ? v.toFixed(1) : v);
    const fmtDT = s => s ? String(s).replace('T',' ').slice(0,19) : '-';

    onMount(async()=>{ syncFromQS(); await loadChannels(); await fetchAll(); });
</script>

<svelte:head><title>전체 통계</title></svelte:head>

<section class="section pt-3 pb-4">
    <div class="container">
        <div class="box mb-0">
            <form class="columns is-variable is-2 is-multiline" on:submit|preventDefault={submit}>
                <div class="column is-12-tablet is-6-desktop">
                    <label class="label is-small">기간</label>
                    <div class="buttons are-small">
                        <button type="button" class="button is-link is-light" on:click={()=>quick('today')}>오늘</button>
                        <button type="button" class="button" on:click={()=>quick('yesterday')}>어제</button>
                        <button type="button" class="button" on:click={()=>quick('week')}>7일</button>
                        <button type="button" class="button" on:click={()=>quick('mon')}>30일</button>
                    </div>
                    <div class="field is-grouped mt-1">
                        <input class="input is-small" type="date" bind:value={from} />
                        <input class="input is-small" type="date" bind:value={to} />
                    </div>
                </div>

                <div class="column is-12-tablet is-3-desktop">
                    <label class="label is-small">채널</label>
                    <div class="select is-fullwidth is-small">
                        <select bind:value={channel}>
                            <option value="">전체 채널</option>
                            {#each channels as c}
                                <option value={c.code}>{c.name} ({c.code})</option>
                            {/each}
                        </select>
                    </div>
                </div>

                <div class="column is-12-tablet is-3-desktop">
                    <label class="label is-small">상품 검색</label>
                    <div class="field has-addons">
                        <input class="input is-small" type="search" bind:value={q} placeholder="상품명 또는 SKU" />
                        <button class="button is-link is-small" type="submit">검색</button>
                        <button class="button is-small" type="button" on:click={reset}>초기화</button>
                    </div>
                    <div class="field is-grouped is-align-items-center mt-1">
                        <label class="label is-small mr-2">Top N</label>
                        <input class="input is-small" style="width:90px" type="number" min="5" max="50" bind:value={limit} on:change={submit} />
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>

<section class="section pt-5">
    <div class="container">
        {#if error}<article class="message is-danger"><div class="message-body">{error}</div></article>{/if}

        <!-- KPI -->
        <div class="columns is-multiline">
            <div class="column is-12-mobile is-6-tablet is-3-desktop"><div class="card kpi"><div class="card-content">
                <p class="is-size-7 has-text-grey">어제 주문건</p><p class="title is-3">{kpi.yesterday_orders||0}</p><p class="is-size-7 has-text-grey">D-1</p>
            </div></div></div>
            <div class="column is-12-mobile is-6-tablet is-3-desktop"><div class="card kpi"><div class="card-content">
                <p class="is-size-7 has-text-grey">오늘 주문건</p><p class="title is-3">{kpi.today_orders||0}</p><p class="is-size-7 has-text-grey">ordered_at 기준</p>
            </div></div></div>
            <div class="column is-12-mobile is-6-tablet is-3-desktop"><div class="card kpi"><div class="card-content">
                <p class="is-size-7 has-text-grey">일주일 주문건</p><p class="title is-3">{kpi.week_orders||0}</p><p class="is-size-7 has-text-grey">최근 7일</p>
            </div></div></div>
            <div class="column is-12-mobile is-6-tablet is-3-desktop"><div class="card kpi"><div class="card-content">
                <p class="is-size-7 has-text-grey">활성 채널 수</p><p class="title is-3">{kpi.channels_total||0}</p><p class="is-size-7 has-text-grey">현재</p>
            </div></div></div>
        </div>

        <!-- 표들 -->
        <div class="columns">
            <section class="column is-12-tablet is-7-desktop">
                <article class="card">
                    <header class="card-header"><h3 class="card-header-title">상품별 주문 Top {limit}</h3></header>
                    <div class="card-content">
                        <div class="table-container">
                            <table class="table is-fullwidth is-striped is-hoverable is-narrow">
                                <thead><tr><th>순위</th><th>SKU</th><th>상품명</th><th class="has-text-right">주문건수</th><th class="has-text-right">비중(%)</th></tr></thead>
                                <tbody>
                                {#if !topProducts?.length}
                                    <tr><td colspan="5" class="has-text-grey">데이터 없음</td></tr>
                                {:else}
                                    {#each topProducts as p}
                                        <tr>
                                            <td>{p.rank}</td>
                                            <td><code class="mono">{p.sku ?? '-'}</code></td>
                                            <td class="is-clamp-2">{p.name ?? '-'}</td>
                                            <td class="has-text-right">{p.order_count ?? 0}</td>
                                            <td class="has-text-right">{pct(p.ratio)}</td>
                                        </tr>
                                    {/each}
                                {/if}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </article>
            </section>

            <section class="column is-12-tablet is-5-desktop">
                <article class="card">
                    <header class="card-header"><h3 class="card-header-title">채널별 주문 분포</h3></header>
                    <div class="card-content">
                        <div class="table-container">
                            <table class="table is-fullwidth is-striped is-hoverable is-narrow">
                                <thead><tr><th>채널</th><th class="has-text-right">주문건수</th><th class="has-text-right">비중(%)</th><th class="has-text-right">전기간 Δ</th></tr></thead>
                                <tbody>
                                {#if !byChannel?.length}
                                    <tr><td colspan="4" class="has-text-grey">데이터 없음</td></tr>
                                {:else}
                                    {#each byChannel as c}
                                        <tr>
                                            <td>{c.channel_name} <span class="has-text-grey is-size-7">({c.channel_code})</span></td>
                                            <td class="has-text-right">{c.orders ?? 0}</td>
                                            <td class="has-text-right">{pct(c.ratio)}</td>
                                            <td class="has-text-right">{c.delta >= 0 ? `+${c.delta}` : c.delta}</td>
                                        </tr>
                                    {/each}
                                {/if}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </article>
            </section>
        </div>

        <section>
            <article class="card">
                <header class="card-header"><h3 class="card-header-title">최근 주문 스냅샷</h3></header>
                <div class="card-content">
                    <div class="table-container">
                        <table class="table is-fullwidth is-hoverable is-narrow">
                            <thead><tr><th>주문번호</th><th>채널</th><th>주문일시</th><th>고객</th><th class="has-text-right">금액</th><th>상태</th></tr></thead>
                            <tbody>
                            {#if !recentOrders?.length}
                                <tr><td colspan="6" class="has-text-grey">데이터 없음</td></tr>
                            {:else}
                                {#each recentOrders as r}
                                    <tr>
                                        <td class="is-clamp-1"><code class="mono">{r.order_no}</code></td>
                                        <td>{r.channel}</td>
                                        <td><span class="mono">{fmtDT(r.ordered_at)}</span></td>
                                        <td>{r.customer}</td>
                                        <td class="has-text-right">{r.amount?.toLocaleString?.() ?? r.amount ?? 0}</td>
                                        <td>{r.status || '-'}</td>
                                    </tr>
                                {/each}
                            {/if}
                            </tbody>
                        </table>
                    </div>
                </div>
            </article>
        </section>
    </div>
</section>

<style>
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "D2Coding", "Noto Sans Mono CJK", monospace; }
    .is-clamp-1, .is-clamp-2 { display:-webkit-box; -webkit-box-orient: vertical; overflow:hidden; }
    .is-clamp-1 { -webkit-line-clamp:1; }
    .is-clamp-2 { -webkit-line-clamp:2; }
    .kpi .title { line-height:1.1; }
    .table.is-narrow td, .table.is-narrow th { padding:.5rem .6rem; }
</style>
