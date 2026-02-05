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
use Illuminate\Support\Str;


class OrderController extends Controller
{
    /** 공통: 관계 로딩 */
    private function withRelations()
    {
        return ['channel:id,name,code', 'product:id,code,name'];
    }

    /** 공통: index 응답용 row 정규화 (channel/product + has_change_logs boolean) */
    private function normalizeIndexRow($row): array
    {
        $r = is_array($row) ? $row : $row->toArray();

        $r['channel_name'] = $r['channel']['name'] ?? null;
        $r['channel_code'] = $r['channel']['code'] ?? null;
        $r['product_code'] = $r['product']['code'] ?? null;

        // exists 서브쿼리 결과(null/1) → bool
        if (array_key_exists('has_change_logs', $r)) {
            $r['has_change_logs'] = !empty($r['has_change_logs']);
        }

        unset($r['channel'], $r['product']);
        return $r;
    }

    /** 공통: export CSV 헤더 */
    private function csvHeader(): array
    {
        return [
            '채널명','채널주문번호','상품명(정규화)','구매자명','구매자휴대폰',
            '수량','수취인명','수취인우편번호','수취인주소','수취인휴대번호',
            '배송요구사항','주문일시(KST)','(KG)박스단위','(Cm)부피단위'
        ];
    }

    /** 공통: export CSV 한 줄 */
    private function csvRow(Order $r): array
    {
        $chName  = $r->channel->name ?? '';
        $pName   = $r->product->name ?? $r->product_title ?? '';
        $orderAt = $r->ordered_at ? $r->ordered_at->timezone('Asia/Seoul')->format('Y-m-d H:i:s') : '';

        $shipping = $r->shipping_request ?? ($r->delivery_message ?? '');

        return [
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
            $shipping,
            $orderAt,
            "",
            "",
        ];
    }

    /** 공통: CSV 스트림 응답 */
    private function streamCsv($query, string $filename): StreamedResponse
    {
        $headers = [
            'Content-Type'                  => 'text/csv; charset=UTF-8',
            'Content-Disposition'           => "attachment; filename=\"$filename\"",
            'Pragma'                        => 'public',
            'Cache-Control'                 => 'no-store, no-cache, must-revalidate',
            'Access-Control-Expose-Headers' => 'Content-Disposition',
        ];

        return response()->stream(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM

            fputcsv($out, $this->csvHeader());

            $i = 0;
            foreach ($query->lazy(1000) as $r) {
                /** @var \App\Models\Order $r */
                fputcsv($out, $this->csvRow($r));
                if ((++$i % 500) === 0) fflush($out);
            }

            fclose($out);
        }, 200, $headers);
    }

    /** 공통: export용 베이스 쿼리 */
    private function baseExportQuery(array $filters)
    {
        return Order::with($this->withRelations())
            ->select('orders.*')
            ->applyFilters($filters);
    }

    /** 공통: 시간(시) 필터 */
    private function applyHourFilter($query, $hourFrom, $hourTo)
    {
        if ($hourFrom === null && $hourTo === null) return $query;

        $hf = is_numeric($hourFrom) ? max(0, min(23, (int)$hourFrom)) : 0;
        $ht = is_numeric($hourTo)   ? max(0, min(23, (int)$hourTo))   : 23;

        return $query->whereBetween(DB::raw('HOUR(ordered_at)'), [$hf, $ht]);
    }

    /** 공통: export 필터 파싱 */
    private function exportFiltersFromRequest(Request $req): array
    {
        $only      = (string) $req->query('only', '');
        $q         = (string) $req->query('q', '');
        $channelId = (string) $req->query('channel_id', '');
        $hasTrack  = $req->query('has_tracking', null);
        $dateFrom  = $req->query('date_from', null);
        $dateTo    = $req->query('date_to', null);

        if ($only === 'no-tracking') {
            $hasTrack = '0';
        }

        return [
            'only' => $only,
            'filters' => [
                'q'            => $q,
                'channel_id'   => $channelId,
                'has_tracking' => $hasTrack,
                'date_from'    => $dateFrom,
                'date_to'      => $dateTo,
            ],
            'has_tracking' => $hasTrack,
            'hour_from'    => $req->query('hour_from', null),
            'hour_to'      => $req->query('hour_to', null),
        ];
    }

    /** 공통: update 응답용 정규화 (channel/product 코드 포함) */
    private function normalizeOrderResponse(Order $order): array
    {
        $order->load($this->withRelations());

        $resp = $order->toArray();
        $resp['channel_name'] = $order->channel->name ?? null;
        $resp['channel_code'] = $order->channel->code ?? null;
        $resp['product_code'] = $order->product->code ?? null;

        unset($resp['channel'], $resp['product']);
        return $resp;
    }

    public function index(Request $req)
    {
        $perPage = (int) $req->query('per_page', 50);
        if ($perPage <= 0 || $perPage > 200) $perPage = 50;

        $filters = [
            'q'            => $req->query('q', ''),
            'channel_id'   => $req->query('channel_id', ''),
            'has_tracking' => $req->query('has_tracking', null),
            'date_from'    => $req->query('date_from', null),
            'date_to'      => $req->query('date_to', null),
        ];

        $sort = (string) $req->query('sort', 'ordered_at');
        $dir  = strtolower((string) $req->query('dir', 'desc'));
        $dir  = in_array($dir, ['asc','desc'], true) ? $dir : 'desc';

        $sortMap = [
            'ordered_at' => 'orders.ordered_at',
            'id'         => 'orders.id',
            'tracking'   => 'orders.tracking_no',
            'product'    => 'orders.product_title',
            'channel'    => 'orders.channel_id',
        ];
        $sortCol = $sortMap[$sort] ?? 'orders.ordered_at';

        $query = Order::with($this->withRelations())
            ->select('orders.*')
            ->addSelect([
                'has_change_logs' => DB::table('order_change_logs as ocl')
                    ->selectRaw('1')
                    ->whereColumn('ocl.order_id', 'orders.id')
                    ->limit(1),
            ])
            ->applyFilters($filters)
            ->orderBy($sortCol, $dir);

        if ($sortCol !== 'orders.id') {
            $query->orderBy('orders.id', 'desc');
        }

        $paginator = $query->paginate($perPage)->appends($req->query());

        $items = collect($paginator->items())
            ->map(fn($row) => $this->normalizeIndexRow($row))
            ->all();

        return ApiResponse::success([
            'data' => $items,
            'pagination' => [
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    public function show(Order $order)
    {
        return ApiResponse::success(new OrderResource($order));
    }

    public function update(UpdateOrderRequest $request, Order $order)
    {
        $data = $request->validated();

        // 이번 요구: 송장번호 + 고객요청사항(배송요구사항) + 기존 유지(메모/상태)
        $keys = ['admin_memo','tracking_no','shipping_request','status_std'];

        // 요청에 포함된 키만 patch 대상으로
        $patch = [];
        foreach ($keys as $k) {
            if (array_key_exists($k, $data)) {
                $patch[$k] = $data[$k];
            }
        }

        if (empty($patch)) {
            return ApiResponse::fail('validation_failed', '수정할 값이 없습니다.', 422, [
                'fields' => ['required_one_of' => $keys]
            ]);
        }

        $now = now();
        $uploadId = (string) Str::uuid(); // 변경 묶음(선택이지만 히스토리 추적에 좋음)

        // 변경된 필드만 로그 생성
        $changeRows = [];
        foreach ($patch as $field => $newVal) {
            $oldVal = $order->{$field};

            $oldStr = $oldVal === null ? '' : (string) $oldVal;
            $newStr = $newVal === null ? '' : (string) $newVal;

            if ($oldStr === $newStr) continue;

            $changeRows[] = [
                'order_id'   => (int) $order->id,
                'upload_id'  => $uploadId,
                'source'     => 'manual',
                'field'      => $field,
                'old_value'  => $oldStr,
                'new_value'  => $newStr,
                'changed_by' => null,   // ✅ 누가 안 씀
                'created_at' => $now,   // ✅ 언제(= 변경시각)
                'updated_at' => $now,
            ];
        }

        if (empty($changeRows)) {
            return ApiResponse::fail('validation_failed', '수정할 값이 없습니다.', 422);
        }

        DB::transaction(function () use ($order, $patch, $changeRows) {
            foreach ($patch as $k => $v) {
                $order->{$k} = $v;
            }
            $order->save();

            DB::table('order_change_logs')->insert($changeRows);
        });

        return ApiResponse::success(
            $this->normalizeOrderResponse($order->refresh()),
            '주문이 갱신되었습니다.'
        );
    }


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
            foreach (array_chunk($payload, 500) as $chunk) {
                $ids = array_column($chunk, 'id');

                DB::table('orders')
                    ->whereIn('id', $ids)
                    ->update([
                        'tracking_no' => DB::raw("CASE id " . implode(' ', array_map(
                                fn($row) => "WHEN {$row['id']} THEN '" . addslashes($row['tracking_no']) . "'",
                                $chunk
                            )) . " END"),
                        'updated_at' => now(),
                    ]);

                $affected += count($chunk);
            }
        });

        return ApiResponse::success(['affected' => (int) $affected], 'bulk updated');
    }

    public function export(Request $req): StreamedResponse
    {
        $ctx = $this->exportFiltersFromRequest($req);

        $query = $this->baseExportQuery($ctx['filters'])
            ->orderByDesc('ordered_at');

        $query = $this->applyHourFilter($query, $ctx['hour_from'], $ctx['hour_to']);

        $ids = array_values(array_filter(array_map('intval', (array) $req->input('ids', []))));
        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        }

        $suffix   = ($ctx['has_tracking'] === '0' || $ctx['only'] === 'no-tracking') ? 'no-tracking_' : '';
        $filename = 'orders_' . $suffix . now('Asia/Seoul')->format('Ymd_His') . '.csv';

        return $this->streamCsv($query, $filename);
    }

    public function exportSelected(Request $req): StreamedResponse
    {
        $ids = array_values(array_filter(array_map('intval', (array) $req->input('ids', []))));
        if (empty($ids)) {
            abort(422, 'ids가 비어 있습니다.');
        }

        // 선택 export는 필터/시간조건 없이 ids만 (원하면 filtersFromRequest 붙여도 됨)
        $query = Order::with($this->withRelations())
            ->select('orders.*')
            ->whereIn('id', $ids)
            ->orderByDesc('ordered_at')
            ->orderByDesc('id');

        $filename = 'orders_selected_' . now('Asia/Seoul')->format('Ymd_His') . '.csv';

        return $this->streamCsv($query, $filename);
    }
}
