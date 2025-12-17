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
            'q'            => $req->query('q', ''),
            'channel_id'   => $req->query('channel_id', ''),
            'has_tracking' => $req->query('has_tracking', null),
            'date_from'    => $req->query('date_from', null),
            'date_to'      => $req->query('date_to', null),
        ];

        // ✅ 정렬 파라미터
        $sort = (string) $req->query('sort', 'ordered_at');
        $dir  = strtolower((string) $req->query('dir', 'desc'));
        $dir  = in_array($dir, ['asc','desc'], true) ? $dir : 'desc';

        // ✅ 허용 정렬 필드 매핑(화이트리스트)
        $sortMap = [
            'ordered_at' => 'orders.ordered_at',
            'id'         => 'orders.id',
            'tracking'   => 'orders.tracking_no',
            'product'    => 'orders.product_title',
            'channel'    => 'orders.channel_id',
        ];
        $sortCol = $sortMap[$sort] ?? 'orders.ordered_at';

        $query = Order::with(['channel:id,name,code', 'product:id,code'])
            ->select('orders.*')
            // ✅ 변경로그 존재 여부(BOOL) 추가
            ->addSelect([
                'has_change_logs' => DB::table('order_change_logs as ocl')
                    ->selectRaw('1')
                    ->whereColumn('ocl.order_id', 'orders.id')
                    ->limit(1)
            ])
            ->applyFilters($filters)
            ->orderBy($sortCol, $dir);

        if ($sortCol !== 'orders.id') {
            $query->orderBy('orders.id', 'desc');
        }

        $paginator = $query->paginate($perPage)->appends($req->query());

        $items = collect($paginator->items())->map(function($row) {
            $r = is_array($row) ? $row : $row->toArray();

            $r['channel_name'] = $r['channel']['name'] ?? null;
            $r['channel_code'] = $r['channel']['code'] ?? null;
            $r['product_code'] = $r['product']['code'] ?? null;

            // ✅ exists 서브쿼리 결과: null/1 형태로 올 수 있어서 boolean으로 정규화
            $r['has_change_logs'] = !empty($r['has_change_logs']);

            unset($r['channel'], $r['product']);
            return $r;
        })->all();

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

        $order->load(['channel:id,name,code', 'product:id,code']);
        $resp = $order->toArray();
        $resp['channel_name'] = $order->channel->name ?? null;
        $resp['channel_code'] = $order->channel->code ?? null;
        $resp['product_code'] = $order->product->code ?? null;
        unset($resp['channel'], $resp['product']);

        return ApiResponse::success($resp, '주문이 갱신되었습니다.');
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
                                function ($row) { return "WHEN {$row['id']} THEN '" . addslashes($row['tracking_no']) . "'"; },
                                $chunk
                            )) . " END"),
                        'updated_at' => now()
                    ]);
                $affected += count($chunk);
            }
        });

        return ApiResponse::success(['affected' => (int) $affected], 'bulk updated');
    }

    public function export(Request $req): StreamedResponse
    {
        $ids       = array_filter(array_map('intval', (array) ($req->input('ids', []))));
        $only      = (string) $req->query('only', '');
        $q         = (string) $req->query('q', '');
        $channelId = (string) $req->query('channel_id', '');
        $hasTrack  = $req->query('has_tracking', null);
        $dateFrom  = $req->query('date_from', null);
        $dateTo    = $req->query('date_to', null);
        $hourFrom  = $req->query('hour_from', null);
        $hourTo    = $req->query('hour_to', null);

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

        if ($hourFrom !== null || $hourTo !== null) {
            $hf = is_numeric($hourFrom) ? max(0, min(23, (int)$hourFrom)) : 0;
            $ht = is_numeric($hourTo)   ? max(0, min(23, (int)$hourTo))   : 23;
            $query->whereBetween(DB::raw('HOUR(ordered_at)'), [$hf, $ht]);
        }

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
            'Access-Control-Expose-Headers' => 'Content-Disposition',
        ];

        return response()->stream(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                '채널명','채널주문번호','상품명(정규화)','구매자명','구매자휴대폰',
                '수량','수취인명','수취인우편번호','수취인주소','수취인휴대번호',
                '배송요구사항','주문일시(KST)'
            ]);

            $i = 0;
            foreach ($query->lazy(1000) as $r) {
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

                if ((++$i % 500) === 0) fflush($out);
            }

            fclose($out);
        }, 200, $headers);
    }


    public function exportSelected(Request $req): StreamedResponse
    {
        $ids = array_values(array_filter(array_map('intval', (array) $req->input('ids', []))));
        if (empty($ids)) {
            abort(422, 'ids가 비어 있습니다.');
        }

        $query = Order::with(['channel:id,name,code', 'product:id,name'])
            ->select('orders.*')
            ->whereIn('id', $ids)
            ->orderByDesc('ordered_at')
            ->orderByDesc('id');

        $filename = 'orders_selected_' . now('Asia/Seoul')->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type'                  => 'text/csv; charset=UTF-8',
            'Content-Disposition'           => "attachment; filename=\"$filename\"",
            'Pragma'                        => 'public',
            'Cache-Control'                 => 'no-store, no-cache, must-revalidate',
            'Access-Control-Expose-Headers' => 'Content-Disposition',
        ];

        return response()->stream(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                '채널명','채널주문번호','상품명(정규화)','구매자명','구매자휴대폰',
                '수량','수취인명','수취인우편번호','수취인주소','수취인휴대번호',
                '배송요구사항','주문일시(KST)'
            ]);

            $i = 0;
            foreach ($query->lazy(1000) as $r) {
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
