<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignProductNameMappingRequest;
use App\Http\Requests\BackfillProductNameMappingRequest;
use App\Http\Requests\IndexProductNameMappingsRequest;
use App\Http\Resources\ProductNameMappingResource;
use App\Models\ProductNameMapping;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;

class ProductNameMappingAdminController extends Controller
{
    /** 목록: 전체 / 미매핑 / 매핑됨 (tri-state) */
    public function index(IndexProductNameMappingsRequest $req)
    {
        $q = ProductNameMapping::query()
            ->with(['channel','product'])
            ->when($req->unmappedFilter() === true,  fn($qq) => $qq->unmapped())
            ->when($req->unmappedFilter() === false, fn($qq) => $qq->whereNotNull('product_id'))
            ->when($req->filled('channel_id'), fn($qq) => $qq->where('channel_id', $req->integer('channel_id')))
            ->search($req->input('q'))
            ->defaultOrder();

        $page = $q->paginate($req->perPage());

        $items = ProductNameMappingResource::collection($page->getCollection())->resolve();
        return ApiResponse::success([
            'data' => $items,
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
            ],
        ]);
    }

    /** 매핑 지정 */
    public function assign(ProductNameMapping $mapping, AssignProductNameMappingRequest $req)
    {
        $mapping->update(['product_id' => $req->productId()]);

        return ApiResponse::success([
            'data' => new ProductNameMappingResource($mapping->fresh(['product','channel'])),
        ], '매핑이 저장되었습니다.');
    }

    /** 매핑 해제 */
    public function unassign(ProductNameMapping $mapping)
    {
        $mapping->update(['product_id' => null]);

        return ApiResponse::success([
            'data' => new ProductNameMappingResource($mapping->fresh(['product','channel'])),
        ], '매핑이 해제되었습니다.');
    }

    private function normPhp(?string $s): string
    {
        // NBSP(0xC2A0) 포함 모든 공백을 ' '로, 연속 공백 압축
        $s = (string)$s;
        $s = str_replace("\xC2\xA0", ' ', $s);          // NBSP → space
        $s = preg_replace('/\s+/u', ' ', $s);           // 연속 공백 1개로
        $s = trim($s);
        return mb_strtolower($s);
    }

    /** 컬럼을 DB측에서 가능한 한 PHP 정규화에 맞추는 SQL 조각 생성 */
    private function normSql(string $col): string
    {
        // COALESCE + 탭/개행/CR/NBSP 제거 → space, 그리고 연속 공백 압축(REPLACE 다중)
        // (MariaDB 호환: REGEXP_REPLACE 없이도 동작)
        $expr = "COALESCE($col,'')";
        $expr = "REPLACE($expr, CHAR(9),  ' ')";        // \t
        $expr = "REPLACE($expr, CHAR(10), ' ')";        // \n
        $expr = "REPLACE($expr, CHAR(13), ' ')";        // \r
        $expr = "REPLACE($expr, 0xC2A0,   ' ')";        // NBSP
        // 연속 공백 압축(여러 번 적용)
        $expr = "REPLACE($expr, '  ', ' ')";
        $expr = "REPLACE($expr, '  ', ' ')";
        $expr = "REPLACE($expr, '  ', ' ')";
        // TRIM + LOWER
        $expr = "LOWER(TRIM($expr))";
        return $expr;
    }


    public function backfill(ProductNameMapping $mapping, BackfillProductNameMappingRequest $req)
    {
        if (!$mapping->product_id) {
            return ApiResponse::fail('validation_failed', 'product_id가 비어 있습니다. 먼저 매핑을 저장하세요.', 422);
        }

        // 비교값 정규화
        $lt = mb_strtolower(trim((string) $mapping->listing_title));
        $ot = $mapping->option_title !== null ? mb_strtolower(trim((string) $mapping->option_title)) : null;

        // 기본 후보: 미매핑 + 동일 채널
        $base = DB::table('orders as o')
            ->whereNull('o.product_id')
            ->where('o.channel_id', $mapping->channel_id);

        // 제품/옵션 조건
        if ($req->mode() === 'exact') {
            $base->whereRaw('LOWER(TRIM(o.product_title)) = ?', [$lt]);

            if ($ot === null || $ot === '') {
                $base->where(function ($w) {
                    $w->whereNull('o.option_title')->orWhere('o.option_title', '');
                });
            } else {
                $base->whereRaw('LOWER(TRIM(o.option_title)) = ?', [$ot]);
            }
        } else { // like
            $base->whereRaw('LOWER(o.product_title) LIKE ?', ['%' . $lt . '%']);
            if ($ot !== null && $ot !== '') {
                $base->whereRaw('LOWER(o.option_title) LIKE ?', ['%' . $ot . '%']);
            }
        }

        // 주문일 범위(옵션)
        if ($from = $req->input('date_from')) {
            $base->where('o.ordered_at', '>=', $from . ' 00:00:00');
        }
        if ($to = $req->input('date_to')) {
            $base->where('o.ordered_at', '<=', $to . ' 23:59:59');
        }

        /**
         * 유니크 키(orders_uq_channel_order_product) 충돌 방지:
         * 동일 (channel_id, channel_order_no) 그룹에서 대표 1건만 업데이트.
         */
        $idQuery = (clone $base)
            ->selectRaw('MIN(o.id) as id')
            ->groupBy('o.channel_id', 'o.channel_order_no');

        // 드라이런: 그룹 개수 = 업데이트 대상 개수
        if ($req->dry()) {
            $count = DB::query()->fromSub($idQuery, 't')->count();
            return ApiResponse::success([
                'updated' => (int) $count,
                'dry'     => true,
            ], '백필 예측 건수입니다.');
        }

        // 실제 업데이트
        $ids = DB::query()->fromSub($idQuery, 't')->pluck('id')->all();
        $affected = 0;

        if (!empty($ids)) {
            DB::transaction(function () use (&$affected, $ids, $mapping) {
                foreach (array_chunk($ids, 1000) as $chunk) {
                    $affected += DB::table('orders')
                        ->whereIn('id', $chunk)
                        ->update([
                            'product_id' => (int) $mapping->product_id,
                            'updated_at' => now(),
                        ]);
                }

                // 최근 백필 시각 기록 (시도 기록 기준, 필요시 $affected>0 조건으로 변경)
                DB::table('product_name_mappings')
                    ->where('id', $mapping->id)
                    ->update(['last_backfilled_at' => now()]);
            });
        } else {
            // 대상이 없어도 백필 시도 시각은 남김(정책에 따라 제거 가능)
            DB::table('product_name_mappings')
                ->where('id', $mapping->id)
                ->update(['last_backfilled_at' => now()]);
        }

        return ApiResponse::success([
            'updated'            => (int) $affected,
            'dry'                => false,
            'last_backfilled_at' => now()->format('Y-m-d H:i:s'),
        ], '백필이 완료되었습니다.');
    }


    /** 삭제 */
    public function destroy(ProductNameMapping $mapping)
    {
        $deleted = (int) $mapping->delete();
        return ApiResponse::success(['deleted' => $deleted], '삭제되었습니다.');
    }
}
