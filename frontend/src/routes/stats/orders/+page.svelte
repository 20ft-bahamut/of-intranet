<script>
    import { onMount } from 'svelte';
    import { fetchJson, qs } from '$lib/api/client.js';
    import { useQueryState } from '$lib/utils/queryState.js';

    const toLocalDate = (d)=> new Date(d.getTime()-d.getTimezoneOffset()*60000).toISOString().slice(0,10);
    const today = toLocalDate(new Date());
    const day = (n)=> toLocalDate(new Date(Date.now()+n*86400000));

    const qsCtl = useQueryState({ from: day(-6), to: today, channel:'', q:'', top:20, recent:20 }, { asNumbers:['top','recent'] });

    let from=day(-6), to=today, channel='', q='', top=20, recent=20;
    let channels=[]; let topProducts=[]; let recents=[]; let byChannel=[]; let loading=false; let error=null;

    function sync(){ const s=qsCtl.snapshot(); from=s.from??from; to=s.to??to; channel=s.channel??''; q=s.q??''; top=Number(s.top??20)||20; recent=Number(s.recent??20)||20; }
    function write(){ qsCtl.set({ from,to,channel,q,top,recent }); qsCtl.write(); }
    const base = ()=>{ const p={from,to}; if(channel) p.channel=channel; if(q.trim()) p.q=q.trim(); return p; };

    async function loadChannels(){ try{ const r=await fetchJson('/channels'); if(r.ok) channels=(r.data||[]).filter(c=>c.is_active);}catch{} }

    async function load(){
        loading=true; error=null;
        try{
            const tp = await fetchJson('/stats/top-products'+qs({ ...base(), limit: top }));
            const ro = await fetchJson('/stats/recent-orders'+qs({ limit: recent }));
            const bc = await fetchJson('/stats/by-channel'+qs(base()));
            if(!tp.ok) throw new Error(tp.message||'top 실패');
            if(!ro.ok) throw new Error(ro.message||'recent 실패');
            if(!bc.ok) throw new Error(bc.message||'by-channel 실패');
            topProducts = tp.data || [];
            recents = ro.data || [];
            byChannel = bc.data || [];
        }catch(e){ error=String(e.message||e); }
        finally{ loading=false; }
    }

    function submit(){ write(); load(); }
    function quick(t){ const m={today:[today,today],yesterday:[day(-1),day(-1)],week:[day(-6),today],mon:[day(-29),today]}; [from,to]=m[t]; submit(); }

    const pct = v => (typeof v==='number'? v.toFixed(1): v);
    const fmtDT = s => s ? String(s).replace('T',' ').slice(0,19) : '-';

    // 총 주문건수/일평균
    $: totalOrders = (byChannel||[]).reduce((s,c)=> s + (c.orders||0), 0);
    function daysBetween(a,b){ const da=new Date(a), db=new Date(b); return Math.max(1, Math.round((db-da)/86400000)+1); }
    $: avgPerDay = Math.round(totalOrders / daysBetween(from,to));

    onMount(async()=>{ sync(); await loadChannels(); await load(); });
</script>

<svelte:head><title>주문 통계</title></svelte:head>

<section class="section">
    <div class="container">
        <div class="box">
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
                    </div>
                    <div class="field is-grouped is-align-items-center mt-1">
                        <label class="label is-small mr-2">Top</label>
                        <input class="input is-small" style="width:80px" type="number" min="5" max="50" bind:value={top} on:change={submit} />
                        <label class="label is-small ml-3 mr-2">최근</label>
                        <input class="input is-small" style="width:80px" type="number" min="5" max="50" bind:value={recent} on:change={submit} />
                    </div>
                </div>
            </form>
        </div>

        <!-- 요약 배지: 총 주문/일평균 -->
        <div class="tags mt-3">
            <span class="tag is-info is-light">총 주문건수: <strong class="ml-1">{totalOrders}</strong></span>
            <span class="tag is-light">일 평균: <strong class="ml-1">{avgPerDay}</strong></span>
        </div>

        {#if error}<article class="message is-danger"><div class="message-body">{error}</div></article>{/if}

        <div class="columns">
            <section class="column is-12-tablet is-7-desktop">
                <article class="card">
                    <header class="card-header"><h3 class="card-header-title">상품 Top {top}</h3></header>
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
                    <header class="card-header"><h3 class="card-header-title">최근 주문 {recent}건</h3></header>
                    <div class="card-content">
                        <div class="table-container">
                            <table class="table is-fullwidth is-hoverable is-narrow">
                                <thead><tr><th>주문번호</th><th>채널</th><th>주문일시</th><th>고객</th><th>상태</th></tr></thead>
                                <tbody>
                                {#if !recents?.length}
                                    <tr><td colspan="5" class="has-text-grey">데이터 없음</td></tr>
                                {:else}
                                    {#each recents as r}
                                        <tr>
                                            <td class="is-clamp-1"><code class="mono">{r.order_no}</code></td>
                                            <td>{r.channel}</td>
                                            <td><span class="mono">{fmtDT(r.ordered_at)}</span></td>
                                            <td>{r.customer}</td>
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
    </div>
</section>

<style>
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "D2Coding", "Noto Sans Mono CJK", monospace; }
    .is-clamp-1, .is-clamp-2 { display:-webkit-box; -webkit-box-orient: vertical; overflow:hidden; }
    .is-clamp-1 { -webkit-line-clamp:1; }
    .is-clamp-2 { -webkit-line-clamp:2; }
    .table.is-narrow td, .table.is-narrow th { padding:.5rem .6rem; }
</style>
