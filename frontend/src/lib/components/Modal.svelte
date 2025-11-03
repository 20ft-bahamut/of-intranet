<script>
    import drag from '$lib/actions/drag.js';
    import { createEventDispatcher, onMount } from 'svelte';

    export let open = false;
    export let title = '';
    export let ariaDescription = '';
    export let width = 640;           // px
    export let maxHeight = 700;       // px
    export let draggable = true;
    export let closeOnBg = true;
    export let closeOnEsc = true;

    const dispatch = createEventDispatcher();
    let card; // modal-card ref

    function close() { dispatch('close'); }
    function keydown(e){
        if (!closeOnEsc) return;
        if (e.key === 'Escape') { e.stopPropagation(); close(); }
    }
    onMount(()=>{ /* noop */ });
</script>

{#if open}
    <div class="modal is-active" role="dialog" aria-modal="true" aria-labelledby="modalTitle" aria-describedby="modalDesc" on:keydown={keydown}>
        <div class="modal-background" on:click={() => closeOnBg && close()}></div>

        <div
                class="modal-card draggable"
                bind:this={card}
                style="
      width: min(92vw, {width}px);
      max-height: min(86vh, {maxHeight}px);
      left: 50%;
      top: 50%;
      transform: translate(-50%,-50%);
    "
        >
            <header class="modal-card-head" use:drag={draggable ? { target: card, clamp: 8, resetOnDblClick: true } : undefined}>
                <p id="modalTitle" class="modal-card-title">{title}</p>
                <button class="delete" type="button" aria-label="닫기" on:click={close}></button>
            </header>

            <section class="modal-card-body">
                {#if ariaDescription}<p id="modalDesc" class="is-sr-only">{ariaDescription}</p>{/if}
                <slot name="body" />
            </section>

            <footer class="modal-card-foot">
                <slot name="footer" />
            </footer>
        </div>
    </div>
{/if}

<style>
    .modal .draggable {
        position: fixed;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .modal-card-head, .modal-card-foot { flex: 0 0 auto; }
    .modal-card-head { cursor: move; user-select: none; }
    .modal-card-body {
        flex: 1 1 auto;
        overflow-y: auto;
        padding: 1rem 1.25rem;
    }
    @media (max-width: 640px){
        .modal .draggable { width: 94vw !important; max-height: 88vh !important; }
    }
</style>
