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
    let result = null;   // { preview, count, stored }
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

    // ===== 업로드 =====
    function apiBase() {
        return (import.meta.env.VITE_API_BASE || '').replace(/\/+$/, '');
    }

    async function upload() {
        if (!selected) { toast.danger('채널을 선택하세요.'); return; }
        if (!file) { toast.danger('엑셀 파일을 선택하세요.'); return; }
        if (selected.is_excel_encrypted && !password) {
            toast.danger('이 채널은 암호화 엑셀입니다. 비밀번호를 입력하세요.');
            return;
        }

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
            toast.success(`업로드 처리 완료 (행 ${result.count}건)`);
        } catch (e) {
            error = e.message || String(e);
            toast.danger(error);
        } finally {
            uploading = false;
        }
    }

    // ===== 테이블 헤더 키 =====
    function previewKeys() {
        if (!result?.preview?.length) return [];
        const keys = Object.keys(result.preview[0]);
        const first = ['channel_code'];
        const rest = keys.filter(k => !first.includes(k));
        return [...first, ...rest];
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
                                        <input id="file" class="file-input" type="file" accept=".xlsx,.xlsm" bind:this={fileInput} on:change={onFileChange} />
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
                                <span class="material-icons" aria-hidden="true">cloud_upload</span>&nbsp;업로드
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
            <h2 class="title is-6">미리보기 <span class="tag is-light">총 {result.count}건</span></h2>
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
        {/if}

        <div class="mt-5">
            <button class="button" type="button" on:click={() => goto('/')}>
                <span class="material-icons">home</span>&nbsp;대시보드로
            </button>
        </div>
    </div>
</section>
