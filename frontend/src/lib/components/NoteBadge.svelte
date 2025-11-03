<script>
    import Tooltip from './Tooltip.svelte';
    export let note = '';
    export let title = '설명';
</script>

{#if note}
    <Tooltip text={note}>
    <span class="tag is-info is-light note-badge" title={title} aria-label={title}>
      <span class="material-icons" aria-hidden="true">sticky_note_2</span>
    </span>
    </Tooltip>
{/if}

<style>
    .note-badge .material-icons { font-size: 18px; line-height: 1; }
</style>
