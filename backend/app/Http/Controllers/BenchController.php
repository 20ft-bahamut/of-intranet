<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class BenchController extends Controller
{
    public function index(Request $request)
    {
        // ✅ 보안: 내부망/IP 제한/로그인 체크 중 하나는 꼭 하세요
        // abort_unless($request->ip() === '1.2.3.4', 403);

        $t0 = hrtime(true);
        $m0 = memory_get_usage(true);

        // 쿼리 로그 수집
        $queries = [];
        $queryTotalMs = 0.0;

        DB::flushQueryLog();
        DB::enableQueryLog();

        DB::listen(function ($query) use (&$queries, &$queryTotalMs) {
            // $query->time 은 ms
            $queryTotalMs += (float) $query->time;
            $queries[] = [
                'sql' => $query->sql,
                'time_ms' => (float) $query->time,
                'bindings_cnt' => is_array($query->bindings) ? count($query->bindings) : 0,
            ];
        });

        $sections = [];

        // 1) 프레임워크/뷰 렌더링 체감용 (가벼운 작업)
        $sections[] = $this->section('php_loop_1e6', function () {
            $x = 0;
            for ($i = 0; $i < 1_000_000; $i++) $x += $i;
            return $x;
        });

        // 2) DB 왕복(간단)
        // 실제 테이블 1개를 지정해서 count 한번 치는 게 제일 현실적입니다.
        // 예: orders 테이블이 있다면 DB::table('orders')->count()
        $table = $request->get('table'); // /bench?table=orders 처럼
        if ($table) {
            $sections[] = $this->section("db_count:$table", function () use ($table) {
                return DB::table($table)->count();
            });
        }

        // 3) Cache (Redis/파일캐시) RTT 확인
        $sections[] = $this->section('cache_put_get', function () {
            $k = 'bench:key:' . uniqid();
            Cache::put($k, 'ok', 60);
            $v = Cache::get($k);
            Cache::forget($k);
            return $v;
        });

        $t1 = hrtime(true);
        $m1 = memory_get_usage(true);

        $totalMs = ($t1 - $t0) / 1e6;
        $memMb = ($m1 - $m0) / 1024 / 1024;

        // 응답에 디버그용 헤더도 박아두면 프론트에서 체감 확인 쉬움
        return response()->json([
            'meta' => [
                'total_ms' => round($totalMs, 3),
                'memory_delta_mb' => round($memMb, 3),
                'php_version' => PHP_VERSION,
                'laravel_env' => app()->environment(),
                'cache_driver' => config('cache.default'),
                'db_connection' => config('database.default'),
            ],
            'sections' => $sections,
            'db' => [
                'query_count' => count($queries),
                'query_total_ms' => round($queryTotalMs, 3),
                // 너무 길면 상위 20개만
                'top_queries' => collect($queries)->sortByDesc('time_ms')->take(20)->values()->all(),
            ],
        ])->withHeaders([
            'X-Bench-Total-Ms' => (string) round($totalMs, 3),
            'X-Bench-DB-Ms' => (string) round($queryTotalMs, 3),
            'X-Bench-DB-Count' => (string) count($queries),
        ]);
    }

    private function section(string $name, callable $fn): array
    {
        $t0 = hrtime(true);
        $m0 = memory_get_usage(true);

        $result = null;
        $error = null;

        try {
            $result = $fn();
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $t1 = hrtime(true);
        $m1 = memory_get_usage(true);

        return [
            'name' => $name,
            'ms' => round(($t1 - $t0) / 1e6, 3),
            'mem_delta_mb' => round((($m1 - $m0) / 1024 / 1024), 3),
            'ok' => $error === null,
            'result_preview' => is_scalar($result) ? $result : (is_null($result) ? null : gettype($result)),
            'error' => $error,
        ];
    }
}
