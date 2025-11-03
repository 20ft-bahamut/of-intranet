<script>
    import { createEventDispatcher } from 'svelte';

    /** 바인딩 값 */
    export let q = '';
    /** placeholder */
    export let placeholder = '검색';
    /** 옵션 배열 [{value:'',label:''}] — 없으면 셀렉트 숨김 */
    export let filterOptions = [];
    /** 바인딩되는 필터 값 */
    export let filter = '';

    /** 배치 순서: 'input-select' | 'select-input' */
    export let order = 'input-select';

    const dispatch = createEventDispatcher();

    function submit() { dispatch('search'); }
    function reset()  { dispatch('reset'); }
</script>

<form class="of-searchbar" role="search" on:submit|preventDefault={submit}>
    <div class="of-sb-controls">
        {#if order === 'select-input'}
            {#if filterOptions?.length}
                <div class="of-sb-select of-sb-select--divider-right">
                    <select bind:value={filter} aria-label="필터">
                        <option value="">전체</option>
                        {#each filterOptions as opt}<option value={opt.value}>{opt.label}</option>{/each}
                    </select>
                </div>
            {/if}
            <input class="input of-sb-input" type="search" bind:value={q} {placeholder} autocomplete="off" />
        {:else}
            <input class="input of-sb-input" type="search" bind:value={q} {placeholder} autocomplete="off" />
            {#if filterOptions?.length}
                <div class="of-sb-select of-sb-select--divider-left">
                    <select bind:value={filter} aria-label="필터">
                        <option value="">전체</option>
                        {#each filterOptions as opt}<option value={opt.value}>{opt.label}</option>{/each}
                    </select>
                </div>
            {/if}
        {/if}

        <button class="button of-sb-btn of-sb-btn--primary" type="submit" aria-label="검색">
            <span class="material-icons">search</span>
        </button>
        <button class="button of-sb-btn" type="button" on:click={reset} aria-label="초기화">
            <span class="material-icons">restart_alt</span>
        </button>
    </div>
</form>
