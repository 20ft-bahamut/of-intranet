<script>
    import { createEventDispatcher, onMount } from 'svelte';
    import Modal from '$lib/components/Modal.svelte';

    export let open = false;
    export let title = '확인';
    /** HTML 허용 */
    export let message = '';
    export let confirmText = '확인';
    export let cancelText = '취소';
    export let confirmClass = 'is-danger';
    export let busy = false;

    // 드래그 가능 여부/크기 옵션 (필요 시 화면별로 덮어쓰기)
    export let draggable = true;
    export let width = 440;
    export let maxHeight = 420;

    const dispatch = createEventDispatcher();
    let confirmBtn;

    function onClose() { dispatch('cancel'); }
    function onConfirm() { if (!busy) dispatch('confirm'); }

    // 모달 열릴 때 확인 버튼에 포커스
    onMount(() => {
        const i = setInterval(() => {
            if (open && confirmBtn) {
                confirmBtn.focus();
                clearInterval(i);
            }
        }, 0);
    });
</script>

<Modal
        {open}
        {draggable}
        {width}
        {maxHeight}
        title={title}
        ariaDescription="확인을 위해 내용을 검토하세요."
        on:close={onClose}
>
    <svelte:fragment slot="body">
        <div class="content" aria-live="polite">
            {@html message}
        </div>
    </svelte:fragment>

    <svelte:fragment slot="footer">
        <div class="buttons is-right actions-gap">
            <button
                    class="button"
                    type="button"
                    on:click={onClose}
                    disabled={busy}
            >{cancelText}</button>

            <button
                    class={"button " + confirmClass + (busy ? " is-loading" : "")}
                    type="button"
                    on:click={onConfirm}
                    bind:this={confirmBtn}
            >{confirmText}</button>
        </div>
    </svelte:fragment>
</Modal>

<style>
    .actions-gap { gap: .75rem; display: inline-flex; flex-wrap: wrap; }
    .content :global(p) { margin: 0; } /* 한 줄 메시지일 때 여백 축소 */
</style>
