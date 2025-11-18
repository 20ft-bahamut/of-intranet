<script>
    import { onMount } from 'svelte';
    import { fetchJson, qs } from '$lib/api/client.js';
    import { useQueryState } from '$lib/utils/queryState.js';

    const toLocalDate = (d)=> new Date(d.getTime()-d.getTimezoneOffset()*60000).toISOString().slice(0,10);
    const today = toLocalDate(new Date());
    const day = (n)=> toLocalDate(new Date(Date.now()+n*86400000));

    const qsCtl = useQueryState({ from: day(-6), to: today, channel:'', q:'' });

    let from=day(-6), to=today, channel='', q='';
    let channels=[], rows=[], loading=false, error=null;

    function sync(){ const s=qsCtl.snapshot(); from=s.from??from; to=s.to??to; channel=s.channel??''; q=s.q??''; }
    function write(){ qsCtl.set({from,to,channel,q}); qsCtl.write(); }

    async function loadChannels(){ try{ const r=await fetchJson('/channels'); if(r.ok) channels=(r.data||[]).filter(c=>c.is_active);}catch{} }
    async function load(){
        loading=true; error=null;
        try{
            const r = await fetchJson('/stats/by-channel'+qs({ from, to, ...(channel?{channel}:{}), ...(q?{q}:{}), }));
            if(!r.ok) throw new Error(r.message||'조회 실패');
            rows = r.data || [];
        }catch(e){ error=String(e.message||e); }
        finally{ loading=false; }
    }
    function submit(){ write(); load(); }
    function quick(t){ const m={today:[today,today],yesterday:[day(-1),day(-1)],week:[day(-6),today],mon:[day(-29),today]}; [from,to]=m[t]; submit(); }

    const pct = v => (typeof v==='number'? v.toFixed(1): v);
    onMount(async()=>{ sync(); await loadChannels(); await load(); });
</script>

<svelte:head><title>채널별 통계</title></svelte:head>

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
                </div>
            </form>
        </div>

        {#if error}<article class="message is-danger"><div class="message-body">{error}</div></article>{/if}

        <article class="card mt-4">
            <header class="card-header"><h3 class="card-header-title">채널 분포</h3></header>
            <div class="card-content">
                <div class="table-container">
                    <table class="table is-fullwidth is-striped is-hoverable is-narrow">
                        <thead><tr><th>채널</th><th class="has-text-right">주문건수</th><th class="has-text-right">비중(%)</th><th class="has-text-right">전기간 Δ</th></tr></thead>
                        <tbody>
                        {#if !rows?.length}
                            <tr><td colspan="4" class="has-text-grey">데이터 없음</td></tr>
                        {:else}
                            {#each rows as c}
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
    </div>
</section>

<style>
    .table.is-narrow td, .table.is-narrow th { padding:.5rem .6rem; }
</style>
