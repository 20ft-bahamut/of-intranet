<script>
    import { onMount } from 'svelte';
    import { goto } from '$app/navigation';
    import { toast } from '$lib/stores/toast.js';
    import { fetchJson } from '$lib/api/client.js';

    // ===== 상태 =====
    let channels = [];
    let loadingChannels = false;

    // 선택 값은 문자열로 통일 (id === "1" 같은 형태)
    let selectedId = '';
    // 선택된 채널 객체는 반응식으로 계산
    let selected = null;
    $: selected = channels.find(c => String(c.id) === String(selectedId)) || null;

    let fileInput;   // <input type="file"> 참조
    let file = null;
    let password = '';

    let uploading = false;
    let committing = false;

    // 업로드 응답 형식: { preview, count, stored, stats?, meta? }
    let result = null;
    let error = null;

    // ===== 채널 로딩 =====
    async function loadChannels() {
        loadingChannels = true; error = null;
        try {
            const res = await fetchJson('/channels');
            if (!res.ok) throw new Error(res.error || '채널 목록 불러오기 실패');
            channels = Array.isArray(res.data) ? res.data.filter(c => !!c.is_active) : [];
            // selected는 위 반응식으로 자동 동기화
        } catch (e) {
            error = e.message || String(e);
            toast.danger(error);
        } finally {
            loadingChannels = false;
        }
    }
    onMount(loadChannels);

    function onFileChange(e) {
        file = e.currentTarget.files?.[0] || null;
        result = null;
    }

    function resetForm() {
        if (fileInput) fileInput.value = '';
        file = null;
        password = '';
        result = null;
    }

    // ===== 공통 =====
    function apiBase() {
        return (import.meta.env.VITE_API_BASE || '').replace(/\/+$/, '');
    }
    function requireSelectedAndFile() {
        if (!selected) { toast.danger('채널을 선택하세요.'); return false; }
        if (!file) { toast.danger('엑셀 파일을 선택하세요.'); return false; }
        if (selected.is_excel_encrypted && !password) {
            toast.danger('이 채널은 암호화 엑셀입니다. 비밀번호를 입력하세요.');
            return false;
        }
        return true;
    }

    // ===== 업로드 (미리보기) =====
    async function upload() {
        if (!requireSelectedAndFile()) return;

        uploading = true; error = null; result = null;
        try {
            const form = new FormData();
            form.append('file', file);
            if (password) form.append('password', password);

            const url = `${apiBase()}/channels/${selected.id}/orders/upload`;
            const res = await fetch(url, { method: 'POST', body: form, headers: { Accept: 'application/json' } });

            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.ok) {
                const msg = data?.message || data?.error || `업로드 실패 (${res.status})`;
                throw new Error(msg);
            }
            result = data.data;
            toast.success(`미리보기 생성 완료 (행 ${result.count ?? result?.preview?.length ?? 0}건)`);
        } catch (e) {
            error = e.message || String(e);
            toast.danger(error);
        } finally {
            uploading = false;
        }
    }

    // ===== 커밋 (DB 반영) =====
    $: canCommit = !!result?.stored && !!selected && !committing;

    async function commit() {
        if (!selected) { toast.danger('채널을 선택하세요.'); return; }
        if (!result?.stored) { toast.danger('업로드 경로가 없습니다. 먼저 미리보기를 생성하세요.'); return; }

        committing = true; error = null;
        try {
            const payload = {
                upload_path: result.stored, // 백엔드 upload()에서 내려준 실제 저장 경로
                // 암호화 채널인 경우에만 전달
                password: selected.is_excel_encrypted ? (password || undefined) : undefined,
            };

            const res = await fetchJson(`/channels/${selected.id}/orders/commit`, {
                method: 'POST',
                body: JSON.stringify(payload),
            });

            if (!res.ok) {
                const msg = res?.message || res?.error || 'DB 반영 실패';
                throw new Error(msg);
            }

            const stats = res?.data?.stats;
            const affected = stats?.affected ?? stats?.received ?? undefined;
            toast.success(`DB 반영 완료${affected != null ? ` (처리: ${affected}건)` : ''}`);

            // 원하면 주문 목록으로 이동:
            // goto('/orders');
        } catch (e) {
            error = e.message || String(e);
            toast.danger(error);
        } finally {
            committing = false;
        }
    }

    // ===== 테이블 헤더 키 =====
    function previewKeys() {
        if (!result?.preview?.length) return [];
        const keys = Object.keys(result.preview[0]);

        // 자주 보는 필드 우선 정렬 + 나머지 뒤에
        const priority = [
            'ordered_at',
            'channel_order_no',
            'product_title',
            'option_title',
            'quantity',
            'receiver_name',
            'receiver_phone',
            'receiver_postcode',
            'receiver_addr_full',
            'tracking_no',
            'status_std',
            'status_src',
            'channel_code',
        ];

        const set = new Set(priority.filter(k => keys.includes(k)));
        keys.forEach(k => set.add(k));
        return Array.from(set);
    }
</script>

<svelte:head>
    <title>주문 업로드 · OF Intranet</title>
    <meta name="description" content="채널을 선택하고 엑셀(.xlsx)을 업로드하여 주문을 미리보기/수집합니다." />
</svelte:head>

<section class="section" aria-labelledby="pageTitle">
    <div class="container">
        <header class="mb-4">
            <h1 id="pageTitle" class="title is-4">
                <span class="material-icons" aria-hidden="true">file_upload</span>&nbsp;주문 업로드
            </h1>
            <p class="subtitle is-6">채널을 선택하고 엑셀(.xlsx)을 업로드하면, 백엔드에서 필드 매핑을 적용해 미리보기를 보여줍니다.</p>
        </header>

        {#if error}
            <article class="message is-danger" aria-live="polite">
                <div class="message-body"><strong>오류:</strong> {error}</div>
            </article>
        {/if}

        <!-- 업로드 폼 -->
        <form on:submit|preventDefault={upload} autocomplete="off">
            <div class="box">
                <div class="columns is-multiline">
                    <!-- 채널 선택 -->
                    <div class="column is-4">
                        <div class="field">
                            <label class="label" for="ch">대상 채널</label>
                            <div class="control">
                                <div class="select is-fullwidth">
                                    <select id="ch" bind:value={selectedId} aria-describedby="ch-help">
                                        <option value="">채널을 선택하세요</option>
                                        {#each channels as c}
                                            <option value={String(c.id)}>{c.name} ({c.code})</option>
                                        {/each}
                                    </select>
                                </div>
                            </div>
                            <p id="ch-help" class="help">
                                {#if loadingChannels}
                                    채널 목록을 불러오는 중…
                                {:else if selected}
                                    시작행: <strong>{selected.excel_data_start_row}</strong>
                                    {#if selected.is_excel_encrypted}
                                        · <span class="tag is-danger is-light">암호화</span>
                                    {:else}
                                        · <span class="tag is-light">일반</span>
                                    {/if}
                                {:else}
                                    활성 채널만 표시됩니다.
                                {/if}
                            </p>
                        </div>
                    </div>

                    <!-- 파일 입력 -->
                    <div class="column is-4">
                        <div class="field">
                            <label class="label" for="file">엑셀 파일</label>
                            <div class="control">
                                <div class="file has-name is-fullwidth">
                                    <label class="file-label">
                                        <input id="file" class="file-input" type="file" accept=".xlsx,.xlsm,.xls,.csv"
                                               bind:this={fileInput} on:change={onFileChange} />
                                        <span class="file-cta">
                                            <span class="file-icon"><span class="material-icons">attach_file</span></span>
                                            <span class="file-label">파일 선택</span>
                                        </span>
                                        <span class="file-name">{file ? file.name : '선택된 파일 없음'}</span>
                                    </label>
                                </div>
                            </div>
                            <p class="help">스마트스토어 등 암호화된 파일도 업로드 가능합니다.</p>
                        </div>
                    </div>

                    <!-- 비밀번호(암호화 채널만) -->
                    <div class="column is-4">
                        <div class="field">
                            <label class="label" for="pw">엑셀 비밀번호</label>
                            <div class="control has-icons-left">
                                <input id="pw" class="input" type="password"
                                       placeholder={selected?.is_excel_encrypted ? '필수 입력' : '암호화 채널에만 필요'}
                                       bind:value={password}
                                       disabled={!selected || !selected.is_excel_encrypted} />
                                <span class="icon is-left"><span class="material-icons">lock</span></span>
                            </div>
                            {#if selected?.is_excel_encrypted}
                                <p class="help">이 채널은 암호화 엑셀입니다. 올바른 비밀번호가 필요합니다.</p>
                            {/if}
                        </div>
                    </div>

                    <!-- 액션 -->
                    <div class="column is-12">
                        <div class="buttons">
                            <button class="button is-link" type="submit" disabled={!selected || !file || uploading}>
                                <span class="material-icons" aria-hidden="true">cloud_upload</span>&nbsp;미리보기 생성
                            </button>
                            <button class="button is-light" type="button" on:click={resetForm}>
                                초기화
                            </button>
                            <a class="button" href="/channels">
                                <span class="material-icons" aria-hidden="true">settings</span>&nbsp;채널 관리
                            </a>
                        </div>
                        {#if uploading}<span class="tag is-info">업로드 처리 중…</span>{/if}
                    </div>
                </div>
            </div>
        </form>

        <!-- 결과 미리보기 -->
        {#if result}
            <h2 class="title is-6">
                미리보기
                <span class="tag is-light">총 {result.count ?? result?.preview?.length ?? 0}건</span>
                {#if result?.stats?.valid != null}
                    <span class="tag is-success is-light">유효 {result.stats.valid}</span>
                {/if}
                {#if result?.stats?.invalid != null}
                    <span class="tag is-danger is-light">무효 {result.stats.invalid}</span>
                {/if}
                {#if result?.meta?.sheet}
                    <span class="tag is-info is-light">시트 {result.meta.sheet}</span>
                {/if}
            </h2>

            {#if result.preview?.length}
                <div class="table-container">
                    <table class="table is-fullwidth is-striped is-hoverable">
                        <thead>
                        <tr>
                            {#each previewKeys() as k}
                                <th scope="col"><code class="mono">{k}</code></th>
                            {/each}
                        </tr>
                        </thead>
                        <tbody>
                        {#each result.preview as row}
                            <tr>
                                {#each previewKeys() as k}
                                    <td class="truncate" title={row[k] ?? ''}>{row[k] ?? ''}</td>
                                {/each}
                            </tr>
                        {/each}
                        </tbody>
                    </table>
                </div>
            {:else}
                <div class="notification is-light">미리보기 데이터가 없습니다.</div>
            {/if}

            <div class="mt-3">
                <span class="tag is-light">저장 위치</span>
                <code class="mono">{result.stored}</code>
            </div>

            <div class="mt-4">
                <button class="button is-success" type="button" on:click={commit} disabled={!canCommit}>
                    <span class="material-icons" aria-hidden="true">save</span>&nbsp;
                    {committing ? 'DB 반영 중…' : 'DB 반영(커밋)'}
                </button>
                <button class="button" type="button" on:click={() => goto('/orders')}>
                    <span class="material-icons">list</span>&nbsp;주문 보기
                </button>
            </div>
        {/if}

        <div class="mt-5">
            <button class="button" type="button" on:click={() => goto('/')}>
                <span class="material-icons">home</span>&nbsp;대시보드로
            </button>
        </div>
    </div>
</section>
