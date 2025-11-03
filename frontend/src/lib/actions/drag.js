// 드래그 액션: 헤더(핸들)를 잡고 .draggable-target를 움직인다.
// 사용법: <header use:drag={{ target: cardRef, clamp: 8, resetOnDblClick: true }} />
export default function drag(node, options = {}) {
    let opt = {
        target: null,           // 필수: 이동시킬 요소 (ref)
        clamp: 8,               // 뷰포트 여백
        resetOnDblClick: true,  // 더블클릭 중앙 복귀
        ...options
    };

    let dragging = false;
    let start = { x: 0, y: 0 };
    let origin = { x: 0, y: 0 };
    let pos = null; // {x,y}를 target.dataset.dragPos에 저장

    function readPos() {
        try {
            const cached = opt.target?.dataset?.dragPos;
            return cached ? JSON.parse(cached) : null;
        } catch { return null; }
    }
    function writePos(p) {
        if (!opt.target) return;
        pos = p;
        if (p) {
            opt.target.style.left = `${p.x}px`;
            opt.target.style.top = `${p.y}px`;
            opt.target.style.transform = 'none';
            opt.target.dataset.dragPos = JSON.stringify(p);
        } else {
            opt.target.style.left = '50%';
            opt.target.style.top = '50%';
            opt.target.style.transform = 'translate(-50%,-50%)';
            delete opt.target.dataset.dragPos;
        }
    }

    function mousedown(e) {
        if (e.button !== 0) return;
        if (!opt.target) return;
        const r = opt.target.getBoundingClientRect();
        dragging = true;
        start = { x: e.clientX, y: e.clientY };
        origin = { x: r.left, y: r.top };
        // 드래그 시작 시 포지션 확정
        writePos(readPos() ?? { x: r.left, y: r.top });
        window.addEventListener('mousemove', mousemove);
        window.addEventListener('mouseup', mouseup);
    }

    function mousemove(e) {
        if (!dragging || !opt.target) return;
        const dx = e.clientX - start.x;
        const dy = e.clientY - start.y;

        const r = opt.target.getBoundingClientRect();
        const w = r.width, h = r.height;
        const min = Number(opt.clamp) || 0;
        const maxX = Math.max(min, window.innerWidth - w - min);
        const maxY = Math.max(min, window.innerHeight - h - min);

        let nx = origin.x + dx;
        let ny = origin.y + dy;
        if (nx < min) nx = min;
        if (ny < min) ny = min;
        if (nx > maxX) nx = maxX;
        if (ny > maxY) ny = maxY;

        writePos({ x: nx, y: ny });
    }

    function mouseup() {
        dragging = false;
        window.removeEventListener('mousemove', mousemove);
        window.removeEventListener('mouseup', mouseup);
    }

    function dblclick() {
        if (opt.resetOnDblClick) writePos(null);
    }

    node.addEventListener('mousedown', mousedown);
    if (opt.resetOnDblClick) node.addEventListener('dblclick', dblclick);

    // 초기 중앙 배치
    if (opt.target) writePos(readPos());

    return {
        update(newOpt) { opt = { ...opt, ...newOpt }; if (opt.target && !readPos()) writePos(null); },
        destroy() {
            node.removeEventListener('mousedown', mousedown);
            node.removeEventListener('dblclick', dblclick);
            mouseup();
        }
    };
}
