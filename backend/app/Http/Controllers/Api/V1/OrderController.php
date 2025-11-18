<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function index(Request $req)
    {
        $perPage = (int) $req->query('per_page', 50);
        if ($perPage <= 0 || $perPage > 200) $perPage = 50;

        $filters = [
            'q' => $req->query('q', ''),
            'channel_id' => $req->query('channel_id', ''),
            'has_tracking' => $req->query('has_tracking', null),
            'date_from' => $req->query('date_from', null),
            'date_to' => $req->query('date_to', null),
        ];

        // Eloquent + 관계 로딩 (N+1 방지)
        $query = Order::with(['channel:id,name,code', 'product:id,code'])
            ->select('orders.*') // 명시적으로 orders 테이블 컬럼 사용
            ->applyFilters($filters)
            ->orderByDesc('id');

        $paginator = $query->paginate($perPage)->appends($req->query());

        // items에 channel_name, channel_code, product_code를 섞어 전달 (프론트 편의)
        $items = collect($paginator->items())->map(function($row) {
            // $row은 모델 인스턴스일 수 있으니 배열화
            $r = is_array($row) ? $row : $row->toArray();

            $r['channel_name'] = $r['channel']['name'] ?? null;
            $r['channel_code'] = $r['channel']['code'] ?? null;
            $r['product_code'] = $r['product']['code'] ?? null;
            unset($r['channel'], $r['product']); // 중복 제거(선택)
            return $r;
        })->all();

        return ApiResponse::success([
            'data' => $items,
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }


    /**
     * GET /api/v1/orders/{order}
     */
    public function show(Order $order)
    {
        return ApiResponse::success(new OrderResource($order));
    }

    /**
     * PATCH /api/v1/orders/{order}
     * 허용: admin_memo, tracking_no, status_std
     * - tracking_no는 빈 값이면 기존값 보존(덮어쓰지 않음)
     */
    public function update(UpdateOrderRequest $request, Order $order)
    {
        // 유효성 통과한 값만 받기
        $data = $request->validated();

        // 전달된 키만 업데이트 (null 들어오면 null로 세팅)
        $dirty = false;
        foreach (['admin_memo','tracking_no','status_std'] as $k) {
            if (array_key_exists($k, $data)) {
                $order->{$k} = $data[$k];
                $dirty = true;
            }
        }

        if (!$dirty) {
            return ApiResponse::fail('validation_failed', '수정할 값이 없습니다.', 422, [
                'fields' => ['required_one_of' => ['admin_memo','tracking_no','status_std']]
            ]);
        }

        $order->save();

        // 응답 편의: 채널/상품 코드 포함
        $order->load(['channel:id,name,code', 'product:id,code']);
        $resp = $order->toArray();
        $resp['channel_name'] = $order->channel->name ?? null;
        $resp['channel_code'] = $order->channel->code ?? null;
        $resp['product_code'] = $order->product->code ?? null;
        unset($resp['channel'], $resp['product']);

        return ApiResponse::success($resp, '주문이 갱신되었습니다.');
    }

    /**
     * (선택) POST /api/v1/orders/bulk/tracking
     * body: { items: [ {id, tracking_no} ... ] }
     * 빈 tracking_no는 무시(보존). 존재하는 것만 업서트/업데이트.
     */
    public function bulkUpdateTracking(Request $req)
    {
        $items = $req->input('items', []);
        if (!is_array($items) || empty($items)) {
            return ApiResponse::fail('validation_failed', 'items가 비어 있습니다.', 422, ['items' => ['required']]);
        }

        $now = now();
        $payload = [];
        foreach ($items as $it) {
            $id  = (int) ($it['id'] ?? 0);
            $trk = isset($it['tracking_no']) ? trim((string) $it['tracking_no']) : '';
            if ($id <= 0 || $trk === '') continue;

            $payload[] = [
                'id'          => $id,
                'tracking_no' => $trk,
                'updated_at'  => $now,
            ];
        }

        if (empty($payload)) {
            return ApiResponse::fail('validation_failed', '업데이트 가능한 항목이 없습니다.', 422);
        }

        $affected = 0;
        DB::transaction(function () use (&$affected, $payload) {
            // id 기준 부분 업데이트
            foreach (array_chunk($payload, 500) as $chunk) {
                $ids = array_column($chunk, 'id');
                // id in (...) 에 대해 tracking_no만 업데이트
                DB::table('orders')
                    ->whereIn('id', $ids)
                    ->update(['tracking_no' => DB::raw("CASE id " . implode(' ', array_map(
                            function ($row) { return "WHEN {$row['id']} THEN '" . addslashes($row['tracking_no']) . "'"; },
                            $chunk
                        )) . " END"), 'updated_at' => now()]);
                $affected += count($chunk);
            }
        });

        return ApiResponse::success(['affected' => (int) $affected], 'bulk updated');
    }


    // ① 현재 필터(페이지 무시) 기반 전체/부분 내보내기
    public function export(Request $req): StreamedResponse
    {
        // ── 1) 파라미터 받기: 선택/전체/송장없음만 + 공통 필터 + 시간대
        $ids       = array_filter(array_map('intval', (array) ($req->input('ids', []))));
        $only      = (string) $req->query('only', ''); // 'no-tracking' | ''
        $q         = (string) $req->query('q', '');
        $channelId = (string) $req->query('channel_id', '');
        $hasTrack  = $req->query('has_tracking', null); // '1' | '0' | null
        $dateFrom  = $req->query('date_from', null);
        $dateTo    = $req->query('date_to', null);
        $hourFrom  = $req->query('hour_from', null); // 0~23
        $hourTo    = $req->query('hour_to', null);   // 0~23

        // 프론트에서 only=no-tracking 오면 최우선으로 반영
        if ($only === 'no-tracking') {
            $hasTrack = '0';
        }

        $filters = [
            'q'            => $q,
            'channel_id'   => $channelId,
            'has_tracking' => $hasTrack,
            'date_from'    => $dateFrom,
            'date_to'      => $dateTo,
        ];

        $query = Order::with(['channel:id,name,code', 'product:id,name'])
            ->select('orders.*')
            ->applyFilters($filters)
            ->orderByDesc('ordered_at');

        // 시간(시) 범위 필터 (DB 시간이 UTC라면 HOUR(ordered_at) 기준의 단순 필터임)
        if ($hourFrom !== null || $hourTo !== null) {
            $hf = is_numeric($hourFrom) ? max(0, min(23, (int)$hourFrom)) : 0;
            $ht = is_numeric($hourTo)   ? max(0, min(23, (int)$hourTo))   : 23;
            $query->whereBetween(DB::raw('HOUR(ordered_at)'), [$hf, $ht]);
        }

        // 선택 다운로드
        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        }

        $suffix   = ($hasTrack === '0' || $only === 'no-tracking') ? 'no-tracking_' : '';
        $filename = 'orders_' . $suffix . now('Asia/Seoul')->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type'                  => 'text/csv; charset=UTF-8',
            'Content-Disposition'           => "attachment; filename=\"$filename\"",
            'Pragma'                        => 'public',
            'Cache-Control'                 => 'no-store, no-cache, must-revalidate',
            // ★ fetch에서 파일명을 읽게 하려면 필요
            'Access-Control-Expose-Headers' => 'Content-Disposition',
        ];

        return response()->stream(function () use ($query) {
            // 출력 스트림
            $out = fopen('php://output', 'w');
            // UTF-8 BOM
            fwrite($out, "\xEF\xBB\xBF");

            // 헤더 라인
            fputcsv($out, [
                '채널명','채널주문번호','상품명(정규화)','구매자명','구매자휴대폰',
                '수량','수취인명','수취인우편번호','수취인주소','수취인휴대번호',
                '배송요구사항','주문일시(KST)'
            ]);

            // 대량 안전: lazy로 당겨오고 주기적으로 flush
            $i = 0;
            foreach ($query->lazy(1000) as $r) {
                /** @var \App\Models\Order $r */
                $chName  = $r->channel->name ?? '';
                $pName   = $r->product->name ?? $r->product_title ?? '';
                $orderAt = $r->ordered_at ? $r->ordered_at->timezone('Asia/Seoul')->format('Y-m-d H:i:s') : '';

                fputcsv($out, [
                    $chName,
                    $r->channel_order_no,
                    $pName,
                    $r->buyer_name,
                    $r->buyer_phone,
                    (int) $r->quantity,
                    $r->receiver_name,
                    $r->receiver_postcode,
                    $r->receiver_addr_full,
                    $r->receiver_phone,
                    $r->delivery_message ?? '',
                    $orderAt,
                ]);

                if ((++$i % 500) === 0) {
                    fflush($out);
                }
            }

            fclose($out);
        }, 200, $headers);
    }


}
