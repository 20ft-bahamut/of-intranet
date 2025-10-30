<script>
    import { fetchJson } from '$lib/api/client.js';

    // 날짜 유틸
    const toLocalDate = (d) => new Date(d.getTime() - d.getTimezoneOffset()*60000).toISOString().slice(0,10);
    const today = toLocalDate(new Date());
    const day = (n) => toLocalDate(new Date(Date.now() + n*24*60*60*1000));

    // 필터 상태
    let startDate = day(-6);  // 최근 7일
    let endDate = today;
    let channel = '';
    let productQ = '';
    let includeTransitions = false;

    // 데이터 상태
    let kpi = { yesterday: 0, today: 0, week: 0, channels_total: 0 };
    let topProducts = [];
    let byChannel = [];
    let recentOrders = [];
    let loading = false;
    let error = null;

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

    async function fetchAll() {
        loading = true; error = null;
        const qs = new URLSearchParams({
            from: startDate, to: endDate,
            ...(channel ? { channel } : {}),
            ...(productQ ? { q: productQ } : {}),
            ...(includeTransitions ? { include_transitions: '1' } : {})
        });

        try {
            const [ov, tp, bc, ro] = await Promise.all([
                fetchJson(`/stats/overview?date=${endDate}`),
                fetchJson(`/stats/top-products?${qs.toString()}&limit=10`),
                fetchJson(`/stats/by-channel?${qs.toString()}`),
                fetchJson(`/stats/recent-orders?limit=10`)
            ]);

            if (!ov.ok) throw new Error(`overview: ${ov.error}`);
            if (!tp.ok) throw new Error(`top-products: ${tp.error}`);
            if (!bc.ok) throw new Error(`by-channel: ${bc.error}`);
            if (!ro.ok) throw new Error(`recent-orders: ${ro.error}`);

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

    // 첫 로드 시 실행
    $: fetchAll();
</script>

<svelte:head>
    <title>주식회사 동해 · 주문 취합 대시보드</title>
    <meta name="description" content="어제/오늘/7일 주문, 채널별·상품별 분포" />
</svelte:head>

<!-- 필터 영역 -->
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
                                <option value="coupang">쿠팡</option>
                                <option value="smartstore">스마트스토어</option>
                                <option value="gnmall">경남몰</option>
                                <option value="nhmall">농협몰</option>
                                <option value="epost">우체국쇼핑</option>
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
                            상태변경 반영 포함(오늘 반영건)
                        </label>
                    </div>
                    <div class="level-right">
                        <button type="reset" class="button is-small" on:click={() => { channel=''; productQ=''; includeTransitions=false; setQuickRange('week'); }}>
                            <span class="material-icons" aria-hidden="true">restart_alt</span>&nbsp;초기화
                        </button>
                    </div>
                </div>
                <p id="filter-help" class="help">기간/채널/검색어는 URL 파라미터로 유지 예정입니다.</p>
            </form>
        </div>
    </div>
</section>

<!-- 본문 -->
<section class="section pt-5" aria-labelledby="kpi-title">
    <div class="container">
        {#if error}
            <article class="message is-danger"><div class="message-body">{error}</div></article>
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
                        <p class="title is-3">{kpi.yesterday}</p>
                        <p class="is-size-7 has-text-grey">D-1</p>
                    </div>
                </div>
            </article>

            <article class="column is-12-mobile is-6-tablet is-3-desktop">
                <div class="card kpi">
                    <div class="card-content">
                        <p class="is-size-7 has-text-grey">오늘 반영건</p>
                        <p class="title is-3">{kpi.today}</p>
                        <p class="is-size-7 has-text-grey">생성 + 상태변경</p>
                    </div>
                </div>
            </article>

            <article class="column is-12-mobile is-6-tablet is-3-desktop">
                <div class="card kpi">
                    <div class="card-content">
                        <p class="is-size-7 has-text-grey">일주일 주문건</p>
                        <p class="title is-3">{kpi.week}</p>
                        <p class="is-size-7 has-text-grey">최근 7일</p>
                    </div>
                </div>
            </article>

            <article class="column is-12-mobile is-6-tablet is-3-desktop">
                <div class="card kpi">
                    <div class="card-content">
                        <p class="is-size-7 has-text-grey">활성 채널 수</p>
                        <p class="title is-3">{kpi.channels_total}</p>
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
                        <table class="table is-fullwidth is-striped is-tight">
                            <thead><tr><th>순위</th><th>SKU</th><th>상품명</th><th class="has-text-right">주문건수</th><th class="has-text-right">비중(%)</th></tr></thead>
                            <tbody>
                            {#if topProducts.length === 0}
                                <tr><td colspan="5" class="has-text-grey">데이터 없음</td></tr>
                            {:else}
                                {#each topProducts as p}
                                    <tr><td>{p.rank}</td><td>{p.sku}</td><td>{p.name}</td><td class="has-text-right">{p.order_count}</td><td class="has-text-right">{p.ratio?.toFixed?.(1)}</td></tr>
                                {/each}
                            {/if}
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>

            <section class="column is-12-tablet is-5-desktop">
                <article class="card">
                    <header class="card-header">
                        <h3 class="card-header-title">채널별 주문 분포</h3>
                    </header>
                    <div class="card-content">
                        <table class="table is-fullwidth is-striped is-tight">
                            <thead><tr><th>채널</th><th class="has-text-right">주문건수</th><th class="has-text-right">비중(%)</th><th class="has-text-right">전일 대비</th></tr></thead>
                            <tbody>
                            {#if byChannel.length === 0}
                                <tr><td colspan="4" class="has-text-grey">데이터 없음</td></tr>
                            {:else}
                                {#each byChannel as c}
                                    <tr><td>{c.channel_name}</td><td class="has-text-right">{c.orders}</td><td class="has-text-right">{c.ratio?.toFixed?.(1)}</td><td class="has-text-right">{c.delta}</td></tr>
                                {/each}
                            {/if}
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>
        </div>

        <!-- 최근 주문 -->
        <section>
            <article class="card">
                <header class="card-header"><h3 class="card-header-title">최근 주문 스냅샷</h3></header>
                <div class="card-content">
                    <table class="table is-fullwidth is-hoverable is-tight">
                        <thead><tr><th>주문번호</th><th>채널</th><th>주문일시</th><th>고객</th><th class="has-text-right">금액</th><th>상태</th></tr></thead>
                        <tbody>
                        {#if recentOrders.length === 0}
                            <tr><td colspan="6" class="has-text-grey">데이터 없음</td></tr>
                        {:else}
                            {#each recentOrders as r}
                                <tr>
                                    <td>{r.order_no}</td>
                                    <td>{r.channel}</td>
                                    <td>{r.ordered_at}</td>
                                    <td>{r.customer}</td>
                                    <td class="has-text-right">{r.amount?.toLocaleString?.() ?? r.amount}</td>
                                    <td>{r.status}</td>
                                </tr>
                            {/each}
                        {/if}
                        </tbody>
                    </table>
                </div>
            </article>
        </section>
    </div>
</section>
