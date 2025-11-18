<script>
    import { onMount } from 'svelte';
    import { fetchJson, qs } from '$lib/api/client.js';

    // ===== 날짜 유틸 (로컬타임 → YYYY-MM-DD) =====
    const toLocalDate = (d) => new Date(d.getTime() - d.getTimezoneOffset()*60000).toISOString().slice(0,10);
    const today = toLocalDate(new Date());
    const day = (n) => toLocalDate(new Date(Date.now() + n*24*60*60*1000));

    // ===== 필터 상태 =====
    let startDate = day(-6);   // 최근 7일
    let endDate   = today;
    let channel   = '';        // 채널 코드 (예: 'epost')
    let productQ  = '';
    let includeTransitions = false;

    // ===== 데이터 상태 =====
    let channels = [];         // 드롭다운 (활성 채널만)
    let kpi = { yesterday: 0, today: 0, week: 0, channels_total: 0 };
    let topProducts = [];
    let byChannel = [];
    let recentOrders = [];

    let loading = false;
    let error = null;

    // ===== 빠른기간 버튼 =====
    function setQuickRange(type) {
        const map = {
            today: [today, today],
            yesterday: [day(-1), day(-1)],
            week: [day(-6), today],
            mon: [day(-29), today]
        };
        [startDate, endDate] = map[type];
        fetchAll();
    }

    // ===== 공통 파라미터 구성 =====
    function baseParams() {
        const p = { from: startDate, to: endDate };
        if (channel) p.channel = channel;         // 채널코드
        if (productQ.trim()) p.q = productQ.trim();
        if (includeTransitions) p.include_transitions = 1; // updated_at 기준
        return p;
    }

    // ===== 데이터 로딩 =====
    async function fetchAll() {
        loading = true; error = null;

        try {
            // 1) KPI: /stats/overview?date=YYYY-MM-DD
            const ov = await fetchJson(`/stats/overview${qs({ date: endDate })}`);
            if (!ov.ok) throw new Error(ov.message || 'overview 실패');

            // 2) Top N: /stats/top-products?from&to&channel&q&include_transitions&limit
            const tp = await fetchJson(`/stats/top-products${qs({ ...baseParams(), limit: 10 })}`);
            if (!tp.ok) throw new Error(tp.message || 'top-products 실패');

            // 3) 채널 분포: /stats/by-channel?from&to&...
            const bc = await fetchJson(`/stats/by-channel${qs(baseParams())}`);
            if (!bc.ok) throw new Error(bc.message || 'by-channel 실패');

            // 4) 최근 주문: /stats/recent-orders?limit
            const ro = await fetchJson(`/stats/recent-orders${qs({ limit: 10 })}`);
            if (!ro.ok) throw new Error(ro.message || 'recent-orders 실패');

            // 바인딩
            kpi = {
                yesterday: ov.data?.yesterday_orders ?? 0,
                today: ov.data?.today_orders ?? 0,
                week: ov.data?.week_orders ?? 0,
                channels_total: ov.data?.channels_total ?? 0
            };
            topProducts = Array.isArray(tp.data) ? tp.data : [];
            byChannel = Array.isArray(bc.data) ? bc.data : [];
            recentOrders = Array.isArray(ro.data) ? ro.data : [];
        } catch (e) {
            error = String(e.message || e);
        } finally {
            loading = false;
        }
    }

    async function loadChannels() {
        try {
            const res = await fetchJson('/channels');
            if (!res.ok) throw new Error(res.message || '채널 목록 실패');
            channels = (res.data || []).filter(c => c.is_active).map(c => ({ id: c.id, name: c.name, code: c.code }));
        } catch (e) {
            // 채널 실패해도 대시는 동작해야 하므로 에러만 표시하고 계속 진행
            error = error ?? String(e.message || e);
        }
    }

    function resetAll() {
        channel = ''; productQ = ''; includeTransitions = false;
        setQuickRange('week');
    }

    const fmtPercent = (v) => (typeof v === 'number' ? v.toFixed(1) : v);
    const fmtInt = (v) => (v ?? 0);
    const fmtDateTime = (s) => (s ? String(s).replace('T',' ').slice(0,19) : '-');

    onMount(async () => {
        await loadChannels();
        await fetchAll();
    });
</script>

<svelte:head>
    <title>주식회사 동해 · 주문 취합 대시보드</title>
    <meta name="description" content="어제/오늘/7일 주문, 채널별·상품별 분포" />
</svelte:head>

<!-- ===== 필터 영역 ===== -->
<section class="section pt-3 pb-4 sticky-filters" aria-labelledby="filter-title">
    <div class="container">
        <div class="box mb-0">
            <h2 id="filter-title" class="is-size-6 has-text-weight-semibold mb-3">
                <span class="material-icons" aria-hidden="true">tune</span>&nbsp;필터
            </h2>

            <form role="search" aria-describedby="filter-help" autocomplete="on" on:submit|preventDefault={fetchAll}>
                <div class="columns is-variable is-2 is-multiline">
                    <!-- 기간 -->
                    <fieldset class="column is-12-mobile is-4-tablet">
                        <legend class="label is-small">기간</legend>
                        <div class="field is-grouped is-grouped-multiline" role="group" aria-label="빠른 기간 선택">
                            <div class="control">
                                <button type="button" class="button is-small is-link is-light" on:click={() => setQuickRange('today')}>
                                    <span class="material-icons" aria-hidden="true">today</span>&nbsp;오늘
                                </button>
                            </div>
                            <div class="control">
                                <button type="button" class="button is-small" on:click={() => setQuickRange('yesterday')}>
                                    <span class="material-icons" aria-hidden="true">event_busy</span>&nbsp;어제
                                </button>
                            </div>
                            <div class="control">
                                <button type="button" class="button is-small" on:click={() => setQuickRange('week')}>
                                    <span class="material-icons" aria-hidden="true">date_range</span>&nbsp;7일
                                </button>
                            </div>
                            <div class="control">
                                <button type="button" class="button is-small" on:click={() => setQuickRange('mon')}>
                                    <span class="material-icons" aria-hidden="true">calendar_view_month</span>&nbsp;30일
                                </button>
                            </div>
                        </div>
                        <div class="field is-grouped mt-1" role="group" aria-label="사용자 지정 기간">
                            <p class="control is-expanded">
                                <label for="start-date" class="is-hidden">시작일</label>
                                <input id="start-date" class="input is-small" type="date" bind:value={startDate} required />
                            </p>
                            <p class="control is-expanded">
                                <label for="end-date" class="is-hidden">종료일</label>
                                <input id="end-date" class="input is-small" type="date" bind:value={endDate} required />
                            </p>
                        </div>
                    </fieldset>

                    <!-- 채널 -->
                    <fieldset class="column is-12-mobile is-4-tablet">
                        <legend class="label is-small">채널</legend>
                        <label class="is-sr-only" for="channel-select">채널 선택</label>
                        <div class="select is-fullwidth is-small">
                            <select id="channel-select" bind:value={channel}>
                                <option value="">전체 채널</option>
                                {#each channels as c}
                                    <option value={c.code}>{c.name} ({c.code})</option>
                                {/each}
                            </select>
                        </div>
                    </fieldset>

                    <!-- 상품 검색 -->
                    <fieldset class="column is-12-mobile is-4-tablet">
                        <legend class="label is-small">상품 검색</legend>
                        <div class="field has-addons">
                            <p class="control is-expanded">
                                <input id="product-q" class="input is-small" type="search" bind:value={productQ} placeholder="상품명 또는 SKU" />
                            </p>
                            <p class="control">
                                <button type="submit" class="button is-small is-link" aria-label="검색 실행">
                                    <span class="material-icons" aria-hidden="true">search</span>
                                </button>
                            </p>
                        </div>
                    </fieldset>
                </div>

                <!-- 추가 옵션 -->
                <div class="level mt-2">
                    <div class="level-left">
                        <label class="checkbox is-small" for="include-transitions">
                            <input id="include-transitions" type="checkbox" bind:checked={includeTransitions}>
                            상태변경 반영 포함(기간 내 updated_at 기준)
                        </label>
                    </div>
                    <div class="level-right">
                        <button type="reset" class="button is-small" on:click={resetAll}>
                            <span class="material-icons" aria-hidden="true">restart_alt</span>&nbsp;초기화
                        </button>
                    </div>
                </div>
                <p id="filter-help" class="help">기간/채널/검색어는 향후 URL 파라미터와 연동 예정입니다.</p>
            </form>
        </div>
    </div>
</section>

<!-- ===== 본문 ===== -->
<section class="section pt-5" aria-labelledby="kpi-title">
    <div class="container">
        {#if error}
            <article class="message is-danger" aria-live="polite"><div class="message-body">{error}</div></article>
        {/if}

        <h2 id="kpi-title" class="is-size-5 has-text-weight-semibold mb-3">
            <span class="material-icons" aria-hidden="true">summarize</span>&nbsp;핵심 지표
            {#if loading}<span class="tag is-info ml-2">Loading…</span>{/if}
        </h2>

        <!-- KPI -->
        <div class="columns is-multiline">
            <article class="column is-12-mobile is-6-tablet is-3-desktop">
                <div class="card kpi">
                    <div class="card-content">
                        <p class="is-size-7 has-text-grey">어제 주문건</p>
                        <p class="title is-3">{fmtInt(kpi.yesterday)}</p>
                        <p class="is-size-7 has-text-grey">D-1</p>
                    </div>
                </div>
            </article>

            <article class="column is-12-mobile is-6-tablet is-3-desktop">
                <div class="card kpi">
                    <div class="card-content">
                        <p class="is-size-7 has-text-grey">오늘 반영건</p>
                        <p class="title is-3">{fmtInt(kpi.today)}</p>
                        <p class="is-size-7 has-text-grey">생성 + 상태변경</p>
                    </div>
                </div>
            </article>

            <article class="column is-12-mobile is-6-tablet is-3-desktop">
                <div class="card kpi">
                    <div class="card-content">
                        <p class="is-size-7 has-text-grey">일주일 주문건</p>
                        <p class="title is-3">{fmtInt(kpi.week)}</p>
                        <p class="is-size-7 has-text-grey">최근 7일</p>
                    </div>
                </div>
            </article>

            <article class="column is-12-mobile is-6-tablet is-3-desktop">
                <div class="card kpi">
                    <div class="card-content">
                        <p class="is-size-7 has-text-grey">활성 채널 수</p>
                        <p class="title is-3">{fmtInt(kpi.channels_total)}</p>
                        <p class="is-size-7 has-text-grey">필터 반영</p>
                    </div>
                </div>
            </article>
        </div>

        <!-- 상품 Top 10 / 채널 분포 -->
        <div class="columns">
            <section class="column is-12-tablet is-7-desktop">
                <article class="card">
                    <header class="card-header">
                        <h3 class="card-header-title">상품별 주문 Top 10</h3>
                    </header>
                    <div class="card-content">
                        <div class="table-container">
                            <table class="table is-fullwidth is-striped is-hoverable is-narrow">
                                <thead><tr><th>순위</th><th>SKU</th><th>상품명</th><th class="has-text-right">주문건수</th><th class="has-text-right">비중(%)</th></tr></thead>
                                <tbody>
                                {#if topProducts.length === 0}
                                    <tr><td colspan="5" class="has-text-grey">데이터 없음</td></tr>
                                {:else}
                                    {#each topProducts as p}
                                        <tr>
                                            <td>{p.rank}</td>
                                            <td><code class="mono">{p.sku}</code></td>
                                            <td class="is-clamp-2">{p.name}</td>
                                            <td class="has-text-right">{fmtInt(p.order_count)}</td>
                                            <td class="has-text-right">{fmtPercent(p.ratio)}</td>
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
                    <header class="card-header">
                        <h3 class="card-header-title">채널별 주문 분포</h3>
                    </header>
                    <div class="card-content">
                        <div class="table-container">
                            <table class="table is-fullwidth is-striped is-hoverable is-narrow">
                                <thead><tr><th>채널</th><th class="has-text-right">주문건수</th><th class="has-text-right">비중(%)</th><th class="has-text-right">전일 대비</th></tr></thead>
                                <tbody>
                                {#if byChannel.length === 0}
                                    <tr><td colspan="4" class="has-text-grey">데이터 없음</td></tr>
                                {:else}
                                    {#each byChannel as c}
                                        <tr>
                                            <td>{c.channel_name} <span class="has-text-grey is-size-7">({c.channel_code})</span></td>
                                            <td class="has-text-right">{fmtInt(c.orders)}</td>
                                            <td class="has-text-right">{fmtPercent(c.ratio)}</td>
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

        <!-- 최근 주문 -->
        <section>
            <article class="card">
                <header class="card-header"><h3 class="card-header-title">최근 주문 스냅샷</h3></header>
                <div class="card-content">
                    <div class="table-container">
                        <table class="table is-fullwidth is-hoverable is-narrow">
                            <thead><tr><th>주문번호</th><th>채널</th><th>주문일시</th><th>고객</th><th class="has-text-right">금액</th><th>상태</th></tr></thead>
                            <tbody>
                            {#if recentOrders.length === 0}
                                <tr><td colspan="6" class="has-text-grey">데이터 없음</td></tr>
                            {:else}
                                {#each recentOrders as r}
                                    <tr>
                                        <td class="is-clamp-1"><code class="mono">{r.order_no}</code></td>
                                        <td>{r.channel}</td>
                                        <td><span class="mono">{fmtDateTime(r.ordered_at)}</span></td>
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
    .is-clamp-1, .is-clamp-2 {
        display: -webkit-box; -webkit-box-orient: vertical; overflow: hidden;
    }
    .is-clamp-1 { -webkit-line-clamp: 1; }
    .is-clamp-2 { -webkit-line-clamp: 2; }

    .kpi .title { line-height: 1.1; }

    /* sticky filter (옵션) */
    .sticky-filters { position: sticky; top: 0; z-index: 10; background: #fff; }

    /* Bulma 표 간격 좀 더 타이트 */
    .table.is-narrow td, .table.is-narrow th { padding: .5rem .6rem; }

    /* 상품 검색: input / button 높이·아이콘 정렬 보정 */
    .field.has-addons .input.is-small { height: 2rem; }
    .field.has-addons .button.is-small { height: 2rem; padding-inline: .6rem; }
    .field.has-addons .button.is-small .material-icons { font-size: 18px; line-height: 1; vertical-align: middle; }

</style>
