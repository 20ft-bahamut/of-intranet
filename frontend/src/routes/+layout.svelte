<script>
    import { page } from '$app/stores';
    let navOpen = false;

    // 현재 경로 활성화 표시
    $: current = $page.url.pathname;
    $: isHome = $page.url.pathname === '/';
    const isActive = (path) => current === path;
    const startsWith = (prefix) => current.startsWith(prefix);
</script>

<header role="banner">
    <nav class="navbar is-white" aria-label="주 내비게이션">
        <div class="navbar-brand">
            <a class="navbar-item has-text-weight-bold" href="/" aria-current={isActive('/') ? 'page' : undefined}>
                <img
                        src="/logo-of.svg"
                        alt="주식회사 동해 로고"
                        style="height: 1.5rem; margin-right: 0.4rem"
                />
                &nbsp;Orderfresh
            </a>

            <!-- 모바일 버거 -->
            <button
                    class={"navbar-burger" + (navOpen ? " is-active" : "")}
                    aria-label="메뉴 토글"
                    aria-controls="mainNav"
                    aria-expanded={navOpen}
                    on:click={() => (navOpen = !navOpen)}
                    type="button">
                <span aria-hidden="true"></span><span aria-hidden="true"></span><span aria-hidden="true"></span>
            </button>
        </div>

        <div id="mainNav" class={"navbar-menu" + (navOpen ? " is-active" : "")} role="menubar">
            <div class="navbar-start">

                <!-- 홈(대시보드) -->
                <a class={"navbar-item" + (isActive('/') ? ' is-active' : '')}
                   role="menuitem"
                   href="/">
                    <span class="material-icons" aria-hidden="true">dashboard</span>&nbsp;대시보드
                </a>

                <!-- 상품관제 -->
                <div class="navbar-item has-dropdown is-hoverable" role="none">
                    <a class={"navbar-link" + (startsWith('/products') || startsWith('/channels') ? ' is-active' : '')}
                       role="menuitem" aria-haspopup="true" aria-expanded="false">
                        <span class="material-icons" aria-hidden="true">inventory</span>&nbsp;상품관제
                    </a>
                    <div class="navbar-dropdown">
                        <a class={"navbar-item" + (isActive('/products') ? ' is-active' : '')} role="menuitem" href="/products">
                            <span class="material-icons" aria-hidden="true">inventory_2</span>&nbsp;상품관리
                        </a>
                        <a class={"navbar-item" + (isActive('/channels') ? ' is-active' : '')} role="menuitem" href="/channels">
                            <span class="material-icons" aria-hidden="true">share</span>&nbsp;채널관리
                        </a>
                    </div>
                </div>

                <!-- 주문관제 -->
                <div class="navbar-item has-dropdown is-hoverable" role="none">
                    <a class={"navbar-link" + (startsWith('/orders') ? ' is-active' : '')}
                       role="menuitem" aria-haspopup="true" aria-expanded="false">
                        <span class="material-icons" aria-hidden="true">assignment</span>&nbsp;주문관제
                    </a>
                    <div class="navbar-dropdown">
                        <a class={"navbar-item" + (isActive('/orders') ? ' is-active' : '')} role="menuitem" href="/orders">
                            <span class="material-icons" aria-hidden="true">receipt_long</span>&nbsp;주문목록
                        </a>
                        <a class={"navbar-item" + (isActive('/orders/upload') ? ' is-active' : '')} role="menuitem" href="/orders/upload">
                            <span class="material-icons" aria-hidden="true">upload_file</span>&nbsp;주문업로드
                        </a>
                        <a class={"navbar-item" + (isActive('/orders/post-tracking-upload') ? ' is-active' : '')} role="menuitem" href="/orders/post-tracking-upload">
                            <span class="material-icons" aria-hidden="true">local_shipping</span>&nbsp;우체국송장업로드
                        </a>
                        <a class={"navbar-item" + (isActive('/orders/transform') ? ' is-active' : '')} role="menuitem" href="/orders/transform">
                            <span class="material-icons" aria-hidden="true">sync_alt</span>&nbsp;주문변환
                        </a>
                    </div>
                </div>

                <!-- 통계 -->
                <div class="navbar-item has-dropdown is-hoverable" role="none">
                    <a class={"navbar-link" + (startsWith('/stats') ? ' is-active' : '')}
                       role="menuitem" aria-haspopup="true" aria-expanded="false">
                        <span class="material-icons" aria-hidden="true">insights</span>&nbsp;통계
                    </a>
                    <div class="navbar-dropdown">
                        <a class={"navbar-item" + (isActive('/stats/overview') ? ' is-active' : '')} role="menuitem" href="/stats/overview">
                            <span class="material-icons" aria-hidden="true">dashboard_customize</span>&nbsp;전체통계
                        </a>
                        <a class={"navbar-item" + (isActive('/stats/by-channel') ? ' is-active' : '')} role="menuitem" href="/stats/by-channel">
                            <span class="material-icons" aria-hidden="true">dataset</span>&nbsp;채널별통계
                        </a>
                        <a class={"navbar-item" + (isActive('/stats/orders') ? ' is-active' : '')} role="menuitem" href="/stats/orders">
                            <span class="material-icons" aria-hidden="true">query_stats</span>&nbsp;주문통계
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </nav>

    {#if isHome}
        <!-- 페이지 타이틀(대시보드 전용) -->
        <section class="section pb-3" aria-labelledby="page-title">
            <div class="container">
                <div class="level">
                    <div class="level-left">
                        <div>
                            <h1 id="page-title" class="title is-4">
                                <span class="material-icons" aria-hidden="true">insights</span>&nbsp;주문 취합 대시보드
                            </h1>
                            <p class="subtitle is-6 is-muted">KST 기준 · 기간/채널/상품 필터</p>
                        </div>
                    </div>
                    <div class="level-right" aria-label="버전 정보">
                        <span class="tag is-light" title="버전">v0.1</span>
                    </div>
                </div>
            </div>
        </section>
    {/if}
</header>

<!-- 본문 -->
<main id="main" role="main">
    <slot />
</main>

<!-- 푸터 -->
<footer class="footer" role="contentinfo">
    <div class="content has-text-centered">
        <p class="is-size-7">
            ©  Orderfresh. powered by INDM Inc.
        </p>
    </div>
</footer>
