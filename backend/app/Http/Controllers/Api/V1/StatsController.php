<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Channel;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    /** KST 날짜(YYYY-MM-DD) → 해당일의 UTC 범위 */
    private function dayRange(string $ymd): array
    {
        $d = Carbon::createFromFormat('Y-m-d', $ymd, 'Asia/Seoul');
        return [$d->copy()->startOfDay()->utc(), $d->copy()->endOfDay()->utc()];
    }

    /** from/to(YYYY-MM-DD) → UTC 범위 + 기준컬럼(ordered_at 고정) */
    private function range(Request $req): array
    {
        $from = $req->query('from', Carbon::now('Asia/Seoul')->subDays(6)->format('Y-m-d'));
        $to   = $req->query('to',   Carbon::now('Asia/Seoul')->format('Y-m-d'));
        $tz = 'Asia/Seoul';
        $start = Carbon::createFromFormat('Y-m-d', $from, $tz)->startOfDay()->utc();
        $end   = Carbon::createFromFormat('Y-m-d', $to,   $tz)->endOfDay()->utc();
        return [$start, $end, 'ordered_at']; // 오늘/어제/주간 모두 ordered_at 기준
    }

    // GET /api/v1/stats/overview?date=YYYY-MM-DD
    public function overview(Request $req)
    {
        $date = $req->query('date', Carbon::now('Asia/Seoul')->format('Y-m-d'));

        // 어제
        $y = Carbon::createFromFormat('Y-m-d', $date, 'Asia/Seoul')->subDay()->format('Y-m-d');
        [$yStart, $yEnd] = $this->dayRange($y);

        // 오늘
        [$todayStart, $todayEnd] = $this->dayRange($date);

        // 최근 7일(오늘 포함)
        $wStartLocal = Carbon::createFromFormat('Y-m-d', $date, 'Asia/Seoul')->subDays(6)->format('Y-m-d');
        [$wStart, $_] = $this->dayRange($wStartLocal);

        $yesterday = Order::whereBetween('ordered_at', [$yStart, $yEnd])->count();
        $today     = Order::whereBetween('ordered_at', [$todayStart, $todayEnd])->count(); // ← fixed
        $week      = Order::whereBetween('ordered_at', [$wStart, $todayEnd])->count();

        $channelsTotal = Channel::where('is_active', true)->count();

        return ApiResponse::success([
            'yesterday_orders' => $yesterday,
            'today_orders'     => $today,          // 프론트가 그대로 씀
            'week_orders'      => $week,
            'channels_total'   => $channelsTotal,
        ]);
    }

    // GET /api/v1/stats/top-products?from=&to=&channel=&q=&limit=10
    public function topProducts(Request $req)
    {
        [$start, $end, $col] = $this->range($req);

        $limit  = max(1, min((int)$req->query('limit', 10), 50));
        $chCode = trim((string)$req->query('channel', ''));
        $q      = trim((string)$req->query('q', ''));

        $base = Order::query()
            ->leftJoin('products as p', 'p.id', '=', 'orders.product_id')
            ->leftJoin('channels as c', 'c.id', '=', 'orders.channel_id')
            ->whereBetween("orders.$col", [$start, $end]);

        if ($chCode !== '') {
            $base->where('c.code', $chCode);
        }
        if ($q !== '') {
            $like = "%$q%";
            $base->where(function ($w) use ($like) {
                $w->where('orders.product_title', 'like', $like)
                    ->orWhere('p.code', 'like', $like)
                    ->orWhere('p.name', 'like', $like);
            });
        }

        // 총 주문수(분모)
        $total = (clone $base)->count();

        // 매핑된 주문: SKU(code) 기준 집계
        $mapped = (clone $base)
            ->whereNotNull('orders.product_id')
            ->select([
                DB::raw('p.code as sku'),
                DB::raw('MAX(COALESCE(p.name, orders.product_title)) as name'),
                DB::raw('COUNT(*) as orders_cnt')
            ])
            ->groupBy('sku');

        // 미매핑 주문: 상품명 기준 집계 (sku는 '-'로 표기)
        $unmapped = (clone $base)
            ->whereNull('orders.product_id')
            ->select([
                DB::raw('"-" as sku'),
                DB::raw('orders.product_title as name'),
                DB::raw('COUNT(*) as orders_cnt')
            ])
            ->groupBy('orders.product_title');

        // 합치기
        $rows = DB::query()->fromSub(
            $mapped->unionAll($unmapped),
            't'
        )
            ->select(['sku','name', DB::raw('SUM(orders_cnt) as orders_cnt')])
            ->groupBy('sku','name')
            ->orderByDesc('orders_cnt')
            ->limit($limit)
            ->get();

        // rank + ratio
        $ranked = [];
        $rank = 1;
        foreach ($rows as $r) {
            $cnt = (int)$r->orders_cnt;
            $ranked[] = [
                'rank'        => $rank++,
                'sku'         => $r->sku ?? '-',
                'name'        => $r->name ?? '-',
                'order_count' => $cnt,
                'ratio'       => $total > 0 ? round($cnt / $total * 100, 1) : 0.0,
            ];
        }

        return ApiResponse::success($ranked);
    }

    // GET /api/v1/stats/by-channel?from=&to=&channel=&q=
    public function byChannel(Request $req)
    {
        [$start, $end, $col] = $this->range($req);

        // 전기간(동일 길이) 계산
        $diffSec = $end->diffInSeconds($start);
        $prevEnd   = $start->copy()->subSecond();
        $prevStart = $prevEnd->copy()->subSeconds($diffSec);

        $chCode  = trim((string)$req->query('channel', ''));
        $q       = trim((string)$req->query('q', ''));

        $base = Order::query()
            ->leftJoin('channels as c', 'c.id', '=', 'orders.channel_id')
            ->leftJoin('products as p', 'p.id', '=', 'orders.product_id')
            ->whereBetween("orders.$col", [$start, $end]);

        if ($chCode !== '') {
            $base->where('c.code', $chCode);
        }
        if ($q !== '') {
            $like = "%$q%";
            $base->where(function ($w) use ($like) {
                $w->where('orders.product_title', 'like', $like)
                    ->orWhere('p.code', 'like', $like);
            });
        }

        $curr = (clone $base)
            ->select([
                'c.id as channel_id', 'c.name as channel_name', 'c.code as channel_code',
                DB::raw('COUNT(*) as orders')
            ])
            ->groupBy('c.id', 'c.name', 'c.code')
            ->get();

        $sum = $curr->sum('orders') ?: 1;

        $prevBase = Order::query()
            ->leftJoin('channels as c', 'c.id', '=', 'orders.channel_id')
            ->leftJoin('products as p', 'p.id', '=', 'orders.product_id')
            ->whereBetween("orders.$col", [$prevStart, $prevEnd]);

        if ($chCode !== '') {
            $prevBase->where('c.code', $chCode);
        }
        if ($q !== '') {
            $like = "%$q%";
            $prevBase->where(function ($w) use ($like) {
                $w->where('orders.product_title', 'like', $like)
                    ->orWhere('p.code', 'like', $like);
            });
        }

        $prev = $prevBase
            ->select(['c.id as channel_id', DB::raw('COUNT(*) as orders')])
            ->groupBy('c.id')
            ->pluck('orders', 'channel_id');

        $out = $curr->map(function ($r) use ($sum, $prev) {
            $orders = (int)$r->orders;
            $before = (int)($prev[$r->channel_id] ?? 0);
            return [
                'channel_id'   => $r->channel_id,
                'channel_name' => $r->channel_name ?? '(미지정)',
                'channel_code' => $r->channel_code ?? '',
                'orders'       => $orders,
                'ratio'        => $orders > 0 ? round($orders / $sum * 100, 1) : 0.0,
                'delta'        => $orders - $before,
            ];
        })->values();

        return ApiResponse::success($out);
    }

    // GET /api/v1/stats/recent-orders?limit=10
    public function recentOrders(Request $req)
    {
        $limit = max(1, min((int)$req->query('limit', 10), 50));

        $rows = Order::query()
            ->with(['channel:id,name,code'])
            ->orderByDesc('ordered_at')
            ->limit($limit)
            ->get([
                'id','channel_id','channel_order_no','ordered_at',
                'buyer_name','receiver_name','product_title','quantity','status_std'
            ]);

        $out = $rows->map(function ($r) {
            return [
                'id'         => $r->id,
                'order_no'   => (string)$r->channel_order_no,
                'channel'    => ($r->channel->name ?? '-') . ' (' . ($r->channel->code ?? '-') . ')',
                'ordered_at' => optional($r->ordered_at)->toDateTimeString(),
                'customer'   => $r->receiver_name ?? $r->buyer_name ?? '-',
                'amount'     => 0,
                'status'     => $r->status_std ?? '-',
            ];
        });

        return ApiResponse::success($out->all());
    }
}
