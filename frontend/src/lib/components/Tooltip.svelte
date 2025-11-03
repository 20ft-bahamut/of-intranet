<script>
    import { onDestroy, tick } from 'svelte';

    export let text = '';
    export let placement = 'top'; // 'top' | 'bottom' | 'left' | 'right'
    export let gap = 8;

    let anchor;   // 트리거 엘리먼트
    let bubble;   // 툴팁 엘리먼트
    let open = false;

    function show() {
        open = true;
        queueMicrotask(async () => {
            await tick();
            mountToBody();
            position();
            addListeners();
        });
    }

    function hide() {
        open = false;
        removeListeners();
        // 닫힐 때는 그냥 display:none 처리 (DOM은 유지)
        if (bubble) bubble.style.display = 'none';
    }

    function mountToBody() {
        if (typeof document === 'undefined' || !bubble) return;
        if (bubble.parentNode !== document.body) {
            document.body.appendChild(bubble);
        }
        bubble.style.display = 'block';
        bubble.style.position = 'fixed';
        bubble.style.zIndex = '99999';
    }

    function addListeners() {
        window.addEventListener('scroll', position, true);
        window.addEventListener('resize', position);
    }
    function removeListeners() {
        window.removeEventListener('scroll', position, true);
        window.removeEventListener('resize', position);
    }

    function position() {
        if (!anchor || !bubble) return;
        const r = anchor.getBoundingClientRect();
        const b = bubble.getBoundingClientRect();

        let p = placement;
        let top, left;

        // 1차 배치
        if (p === 'top')    { top = r.top - b.height - gap; left = r.left; }
        if (p === 'bottom') { top = r.bottom + gap;         left = r.left; }
        if (p === 'left')   { top = r.top;                  left = r.left - b.width - gap; }
        if (p === 'right')  { top = r.top;                  left = r.right + gap; }

        // 화면 밖이면 반대 방향으로 플립
        if (p === 'top'    && top < 8)                                   p = 'bottom';
        if (p === 'bottom' && top + b.height > window.innerHeight - 8)   p = 'top';
        if (p === 'left'   && left < 8)                                  p = 'right';
        if (p === 'right'  && left + b.width > window.innerWidth - 8)    p = 'left';

        // 최종 좌표(클램프)
        if (p === 'top') {
            top = r.top - b.height - gap;
            left = clamp(r.left, 8, window.innerWidth - b.width - 8);
        } else if (p === 'bottom') {
            top = r.bottom + gap;
            left = clamp(r.left, 8, window.innerWidth - b.width - 8);
        } else if (p === 'left') {
            top = clamp(r.top, 8, window.innerHeight - b.height - 8);
            left = r.left - b.width - gap;
        } else if (p === 'right') {
            top = clamp(r.top, 8, window.innerHeight - b.height - 8);
            left = r.right + gap;
        }

        bubble.style.top = `${Math.round(top)}px`;
        bubble.style.left = `${Math.round(left)}px`;
    }

    function clamp(v, min, max) {
        return Math.min(Math.max(v, min), max);
    }

    onDestroy(() => {
        removeListeners();
        if (bubble && bubble.parentNode && bubble.parentNode.removeChild) {
            try { bubble.parentNode.removeChild(bubble); } catch {}
        }
    });
</script>

<span
        bind:this={anchor}
        class="tooltip-anchor"
        aria-describedby={open && text ? 'tooltip' : undefined}
        on:mouseenter={show}
        on:mouseleave={hide}
        on:focus={show}
        on:blur={hide}
>
  <slot/>
</span>

{#if text}
    <!-- Svelte가 관리하는 DOM이지만, 열릴 때 body로 이동시킨다 -->
    <span bind:this={bubble} class="tooltip-bubble" style="display:none">{text}</span>
{/if}

<style>
    .tooltip-anchor { position: relative; display: inline-block; }
    .tooltip-bubble {
        background: #363636;
        color: #fff;
        font-size: .85rem;
        line-height: 1.25;
        padding: .5rem .6rem;
        border-radius: .35rem;
        box-shadow: 0 10px 24px rgba(0,0,0,.25);
        max-width: 420px;
        white-space: pre-wrap;
        pointer-events: none;
    }
</style>
