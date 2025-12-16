<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderChangeLog;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OrderChangeLogController extends Controller
{
    public function index(Request $req, Order $order)
    {
        $perPage = (int) $req->query('per_page', 50);
        if ($perPage <= 0 || $perPage > 200) {
            $perPage = 50;
        }

        $field  = trim((string) $req->query('field', ''));
        $source = trim((string) $req->query('source', ''));
        $from   = trim((string) $req->query('from', '')); // YYYY-MM-DD (KST)
        $to     = trim((string) $req->query('to', ''));   // YYYY-MM-DD (KST)

        $q = OrderChangeLog::query()
            ->where('order_id', $order->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($field !== '') {
            $q->where('field', $field);
        }

        if ($source !== '') {
            $q->where('source', $source);
        }

        /**
         * 날짜 필터
         * - 프론트는 KST 기준
         * - DB는 UTC
         */
        if ($from !== '' || $to !== '') {
            $tz = 'Asia/Seoul';

            $start = $from !== ''
                ? Carbon::createFromFormat('Y-m-d', $from, $tz)
                    ->startOfDay()
                    ->utc()
                : Carbon::create(1970, 1, 1, 0, 0, 0, 'UTC');

            $end = $to !== ''
                ? Carbon::createFromFormat('Y-m-d', $to, $tz)
                    ->endOfDay()
                    ->utc()
                : Carbon::now('UTC');

            $q->whereBetween('created_at', [$start, $end]);
        }

        $p = $q->paginate($perPage)->appends($req->query());

        /**
         * 프론트 diff UI 전용 포맷
         */
        $items = collect($p->items())->map(function (OrderChangeLog $r) {
            return [
                'id'         => (int) $r->id,
                'order_id'   => (int) $r->order_id,
                'field'      => (string) $r->field,
                'old_value'  => $r->old_value,
                'new_value'  => $r->new_value,
                'source'     => (string) $r->source,
                // 프론트는 created_at을 변경 시점으로 사용
                'created_at' => optional($r->created_at)
                    ->timezone('Asia/Seoul')
                    ->format('Y-m-d H:i:s'),
            ];
        })->all();

        return ApiResponse::success([
            'data' => $items,
            'pagination' => [
                'total'        => $p->total(),
                'per_page'     => $p->perPage(),
                'current_page' => $p->currentPage(),
                'last_page'    => $p->lastPage(),
            ],
        ]);
    }
}
