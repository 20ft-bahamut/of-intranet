<script>
    export let open = false;
    export let title = '확인';
    export let message = '';
    export let confirmText = '삭제';
    export let cancelText = '취소';
    export let confirmClass = 'is-danger'; // is-link, is-warning 등
    export let busy = false;

    let firstBtn;

    // ESC로 닫기
    function onKeydown(e) {
        if (e.key === 'Escape') {
            e.stopPropagation();
            dispatch('cancel');
        }
    }

    import { createEventDispatcher } from 'svelte';
    const dispatch = createEventDispatcher();

    $: if (open) queueMicrotask(() => firstBtn?.focus());
</script>

{#if open}
    <div class="modal is-active" role="dialog" aria-modal="true" aria-labelledby="confirmTitle" aria-describedby="confirmDesc" on:keydown={onKeydown}>
        <div class="modal-background" on:click={() => dispatch('cancel')}></div>

        <div class="modal-card">
            <header class="modal-card-head">
                <p id="confirmTitle" class="modal-card-title">{title}</p>
                <button class="delete" type="button" aria-label="닫기" on:click={() => dispatch('cancel')}></button>
            </header>

            <section class="modal-card-body">
                <p id="confirmDesc">{@html message}</p>
            </section>

            <footer class="modal-card-foot actions-gap">
                <button class={"button " + confirmClass} type="button" disabled={busy}
                        on:click={() => dispatch('confirm')} bind:this={firstBtn}>
                    {confirmText}
                </button>
                <button class="button is-light" type="button" disabled={busy}
                        on:click={() => dispatch('cancel')}>
                    {cancelText}
                </button>
            </footer>
        </div>
    </div>
{/if}

<style>
    .modal-card-body p { margin: 0; }

    /* 버튼 간격 넉넉히 + 모바일 줄바꿈 대응 */
    .actions-gap {
        display: flex;               /* Bulma가 이미 flex지만 명시 */
        gap: 0.75rem;                /* ← 여기서 간격 키움 (원하면 1rem) */
        flex-wrap: wrap;             /* 화면 좁으면 다음 줄로 */
        justify-content: flex-start; /* 좌측 정렬(원하면 flex-end) */
    }

    /* 파괴적 버튼(위험) 더 도드라지게 살짝 두껍게 */
    .actions-gap .is-danger {
        font-weight: 600;
    }
</style>
