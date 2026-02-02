<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ProcessChannelExcel;
use App\Http\Controllers\Controller;
use App\Http\Requests\CommitChannelOrdersRequest;
use App\Models\Channel;
use App\Models\Order;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class OrderUploadController extends Controller
{
    /**
     * 업로드 → 미리보기 생성
     * 응답: stored(상대경로), upload_path(절대경로) 제공
     */
    public function upload(Request $req, Channel $channel, ProcessChannelExcel $proc)
    {
        // 암호화 채널: 비밀번호 필수
        if ($channel->is_excel_encrypted && !$req->filled('password')) {
            return ApiResponse::fail('validation_failed', '암호화된 파일은 비밀번호가 필요합니다.', 422, [
                'password' => ['required'],
            ]);
        }

        // 파일 체크
        $file = $req->file('file');
        if (!$file || !$file->isValid()) {
            return ApiResponse::fail('validation_failed', '파일 업로드에 실패했습니다.', 422, [
                'file' => ['upload_failed'],
            ]);
        }

        // 저장 경로 구성
        $disk = (string) config('ofintranet.upload_disk', 'local');
        $root = trim((string) config('ofintranet.upload_root', 'uploads'), '/');

        // 파일명
        $ext   = $file->getClientOriginalExtension() ?: 'xlsx';
        $uuid  = (string) Str::uuid();
        $stamp = now()->format('Ymd_His');
        $filename = $stamp . '_' . $uuid . '.' . $ext;

        // 저장 (상대경로: {root}/{channel_code}/{filename})
        $stored = $file->storeAs($root . '/' . $channel->code, $filename, $disk);
        $abs    = Storage::disk($disk)->path($stored);

        try {
            $password = (string) $req->input('password', '');
            $parsed   = $proc->handle($channel, $abs, $password);
        } catch (Throwable $e) {
            report($e);
            return ApiResponse::fail('server_error', '업로드 처리 중 오류가 발생했습니다.', 500);
        }

        return ApiResponse::success([
            'preview'     => $parsed['preview'] ?? [],
            'count'       => isset($parsed['rows']) ? count($parsed['rows']) : count($parsed['preview'] ?? []),
            'stored'      => $stored,
            'upload_path' => $abs,
            'stats'       => $parsed['stats'] ?? null,
            'meta'        => $parsed['meta'] ?? null,
        ], '업로드 처리 완료');
    }

    /**
     * 전화번호 정규화
     */
    private function normalizePhone(?string $raw): ?string
    {
        if ($raw === null) return null;

        $d = preg_replace('/\D+/', '', $raw ?? '');
        if ($d === '') return null;

        // 82로 시작하면 국내 국번으로 환원 (예: 821012345678 -> 01012345678)
        if (str_starts_with($d, '82')) {
            $d = '0' . substr($d, 2);
        }

        // 02 지역번호(서울)
        if (str_starts_with($d, '02')) {
            if (strlen($d) === 9)  return sprintf('02-%s-%s', substr($d, 2, 3), substr($d, 5, 4));
            if (strlen($d) === 10) return sprintf('02-%s-%s', substr($d, 2, 4), substr($d, 6, 4));
            return $raw;
        }

        // 10자리: 3-3-4
        if (strlen($d) === 10) {
            return sprintf('%s-%s-%s', substr($d, 0, 3), substr($d, 3, 3), substr($d, 6, 4));
        }

        // 11자리: 3-4-4
        if (strlen($d) === 11) {
            return sprintf('%s-%s-%s', substr($d, 0, 3), substr($d, 3, 4), substr($d, 7, 4));
        }

        return $raw;
    }

    /**
     * ordered_at 정규화
     * - 없으면 $fallback 사용
     * - 문자열이면 Carbon parse 시도, 실패 시 fallback
     */
    private function normalizeOrderedAt($value, Carbon $fallback): Carbon
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_numeric($value)) {
            // 엑셀 serial 등 숫자 날짜는 여기서 처리하지 않음(파서에서 이미 처리하는 전제가 일반적)
            // 그래도 들어오면 fallback
            return $fallback;
        }

        if (is_string($value)) {
            try {
                return Carbon::parse($value);
            } catch (Throwable $e) {
                return $fallback;
            }
        }

        return $fallback;
    }

    /**
     * 미리보기 후 → DB 반영(커밋)
     *
     * - UNIQUE KEY: (channel_id, channel_order_no)
     * - tracking_no 없는 재업로드는 기존 tracking_no 덮지 않음(2단계 upsert)
     * - 변경이력(order_change_logs) 기록: created_at을 변경시각으로 사용(※ changed_at 컬럼 없음)
     * - ordered_at 없으면 업로드(커밋) 시각으로 대체
     */
    public function commit(CommitChannelOrdersRequest $req, Channel $channel, ProcessChannelExcel $proc)
    {
        $disk     = (string) config('ofintranet.upload_disk', 'local');
        $rawPath  = (string) $req->input('upload_path', '');
        $password = (string) $req->input('password', '');

        // 경로 해석: 절대경로 우선, 아니면 Storage 상대경로 → 절대경로
        $path = $rawPath;
        if (!$this->isAbsolutePath($path)) {
            $candidate = Storage::disk($disk)->path($path);
            if (File::exists($candidate)) $path = $candidate;
        }

        if (!File::exists($path)) {
            \Log::warning('orders.commit not_found', ['raw' => $rawPath, 'resolved' => $path, 'disk' => $disk]);
            return ApiResponse::fail('not_found', '업로드 파일을 찾을 수 없습니다.', 404);
        }

        try {
            $parsed = $proc->handle($channel, $path, $password);
            $rows   = $parsed['rows'] ?? [];
            if (empty($rows)) {
                return ApiResponse::fail('validation_failed', '유효한 주문 행이 없습니다.', 422, [
                    'rows' => ['empty'],
                ]);
            }
        } catch (Throwable $e) {
            report($e);
            return ApiResponse::fail('server_error', '주문 반영 중 오류가 발생했습니다.', 500);
        }

        $now = now();
        $uploadId  = (string) Str::uuid();        // ✅ 변경이력 배치 묶음
        $changedBy = Auth::id();                  // ✅ 로그인 사용자(없으면 null)
        $source    = 'excel';                     // ✅ 출처

        $validPayload = [];
        $failures = [];
        $reasonAgg = [];

        $orderNos = [];
        $idx = 0;

        $orderedAtFilled = 0;

        // 1) 유효 payload 만들기
        foreach ($rows as $r) {
            $idx++;

            // raw source key 필수
            $rawSourceKey = null;
            foreach (['raw_payload', '_cells', '_raw'] as $k) {
                if (array_key_exists($k, $r)) { $rawSourceKey = $k; break; }
            }
            if ($rawSourceKey === null) {
                $failures[] = [
                    'index'            => $r['_row'] ?? $idx,
                    'channel_order_no' => $r['channel_order_no'] ?? null,
                    'reasons'          => ['raw_payload 누락(엑셀 원본 행 키 없음: raw_payload|_cells|_raw)'],
                ];
                $reasonAgg['raw_payload 누락'] = ($reasonAgg['raw_payload 누락'] ?? 0) + 1;
                continue;
            }

            // 필수값 체크 (ordered_at은 더 이상 필수가 아님)
            $reasons = [];
            if (empty($r['channel_order_no']))   $reasons[] = 'channel_order_no 누락';
            if (empty($r['receiver_name']))      $reasons[] = 'receiver_name 누락';
            if (empty($r['receiver_postcode']))  $reasons[] = 'receiver_postcode 누락';
            if (empty($r['receiver_addr_full'])) $reasons[] = 'receiver_addr_full 누락';
            if (empty($r['receiver_phone']))     $reasons[] = 'receiver_phone 누락';

            if (!empty($reasons)) {
                foreach ($reasons as $rr) $reasonAgg[$rr] = ($reasonAgg[$rr] ?? 0) + 1;
                $failures[] = [
                    'index'            => $r['_row'] ?? $idx,
                    'channel_order_no' => $r['channel_order_no'] ?? null,
                    'reasons'          => $reasons,
                ];
                continue;
            }

            // 원본 payload/json/meta/hash
            $rawSource = $r[$rawSourceKey];
            $rawPayloadJson = is_string($rawSource)
                ? $rawSource
                : json_encode($rawSource, JSON_UNESCAPED_UNICODE);

            $rawMetaArr = $r['raw_meta'] ?? [
                'sheet'        => $parsed['meta']['sheet'] ?? ($r['_sheet'] ?? null),
                'row'          => $r['_row'] ?? $idx,
                'channel_code' => $channel->code,
            ];
            $rawMetaJson = is_string($rawMetaArr) ? $rawMetaArr : json_encode($rawMetaArr, JSON_UNESCAPED_UNICODE);

            $rawHash = $r['raw_hash'] ?? hash('sha256', (string) $rawPayloadJson);

            // 배송요구사항 키 흡수
            $shippingRequest = $r['shipping_request'] ?? ($r['delivery_message'] ?? null);

            // ordered_at: 없으면 커밋 시각으로 대체
            $orderedAt = $this->normalizeOrderedAt($r['ordered_at'] ?? null, $now);
            if (($r['ordered_at'] ?? null) === null || ($r['ordered_at'] ?? '') === '') {
                $orderedAtFilled++;
            }

            $channelOrderNo = (string) $r['channel_order_no'];
            $orderNos[] = $channelOrderNo;

            $validPayload[] = [
                'channel_id'         => $channel->id,
                'channel_order_no'   => $channelOrderNo,
                'product_id'         => isset($r['_product_id']) && $r['_product_id'] !== '' ? (int)$r['_product_id'] : (isset($r['product_id']) && $r['product_id'] !== '' ? (int)$r['product_id'] : null),

                'product_title'      => $r['product_title'] ?? null,
                'option_title'       => $r['option_title'] ?? null,
                'quantity'           => (int)($r['quantity'] ?? 1),
                'tracking_no'        => $r['tracking_no'] ?? null,

                'buyer_name'         => $r['buyer_name'] ?? null,
                'buyer_phone'        => $this->normalizePhone($r['buyer_phone'] ?? null),
                'buyer_postcode'     => $r['buyer_postcode'] ?? null,
                'buyer_addr_full'    => $r['buyer_addr_full'] ?? null,
                'buyer_addr1'        => $r['buyer_addr1'] ?? null,
                'buyer_addr2'        => $r['buyer_addr2'] ?? null,

                'receiver_name'      => $r['receiver_name'] ?? null,
                'receiver_phone'     => $this->normalizePhone($r['receiver_phone'] ?? null),
                'receiver_postcode'  => $r['receiver_postcode'] ?? null,
                'receiver_addr_full' => $r['receiver_addr_full'] ?? null,
                'receiver_addr1'     => $r['receiver_addr1'] ?? null,
                'receiver_addr2'     => $r['receiver_addr2'] ?? null,

                'shipping_request'   => $shippingRequest,
                'customer_note'      => $r['customer_note'] ?? null,
                'admin_memo'         => $r['admin_memo'] ?? null,

                'ordered_at'         => $orderedAt,
                'status_src'         => $r['status_src'] ?? null,
                'status_std'         => $r['status_std'] ?? null,

                'raw_payload'        => $rawPayloadJson,
                'raw_meta'           => $rawMetaJson,
                'raw_hash'           => $rawHash,

                'created_at'         => $now,
                'updated_at'         => $now,
            ];
        }

        $received = count($rows);
        $valid    = count($validPayload);
        $invalid  = count($failures);

        arsort($reasonAgg);
        \Log::info('orders.commit validation summary', [
            'received' => $received,
            'valid'    => $valid,
            'invalid'  => $invalid,
            'top_reasons' => array_slice($reasonAgg, 0, 5, true),
            'ordered_at_filled' => $orderedAtFilled,
        ]);

        if ($valid === 0) {
            return ApiResponse::fail('validation_failed', '모든 행이 검증에서 탈락했습니다.', 422, [
                'failures' => $failures,
                'summary'  => $reasonAgg,
            ]);
        }

        // 2) 기존 주문들 로딩(변경이력 비교용) - (channel_id, channel_order_no)
        $orderNos = array_values(array_unique($orderNos));
        $existingMap = [];

        if (!empty($orderNos)) {
            $existing = Order::query()
                ->where('channel_id', $channel->id)
                ->whereIn('channel_order_no', $orderNos)
                ->get([
                    'id', 'channel_id', 'channel_order_no',
                    'tracking_no',
                    'receiver_name', 'receiver_phone', 'receiver_addr_full',
                    'shipping_request',
                ]);

            foreach ($existing as $ex) {
                $existingMap[$ex->channel_order_no] = $ex;
            }
        }

        // 3) 변경이력 쌓기 (created_at 사용, upload_id/source/changed_by 채움)
        $changeRows = [];
        foreach ($validPayload as $row) {
            $ex = $existingMap[$row['channel_order_no']] ?? null;
            if (!$ex) continue;

            // 비교 대상 필드(필요하면 추가)
            $this->appendChangeRow($changeRows, $ex, $uploadId, $changedBy, 'tracking_no',        $ex->tracking_no,        $row['tracking_no'] ?? null,        $source, $now);
            $this->appendChangeRow($changeRows, $ex, $uploadId, $changedBy, 'receiver_name',      $ex->receiver_name,      $row['receiver_name'] ?? null,      $source, $now);
            $this->appendChangeRow($changeRows, $ex, $uploadId, $changedBy, 'receiver_phone',     $ex->receiver_phone,     $row['receiver_phone'] ?? null,     $source, $now);
            $this->appendChangeRow($changeRows, $ex, $uploadId, $changedBy, 'receiver_addr_full', $ex->receiver_addr_full, $row['receiver_addr_full'] ?? null, $source, $now);
            $this->appendChangeRow($changeRows, $ex, $uploadId, $changedBy, 'shipping_request',   $ex->shipping_request,   $row['shipping_request'] ?? null,   $source, $now);
        }

        // 4) tracking_no 보호 2단계 upsert
        $withTracking    = [];
        $withoutTracking = [];
        foreach ($validPayload as $row) {
            $hasTracking = isset($row['tracking_no']) && $row['tracking_no'] !== null && $row['tracking_no'] !== '';
            if ($hasTracking) $withTracking[] = $row;
            else              $withoutTracking[] = $row;
        }

        $affected = 0;

        try {
            DB::transaction(function () use (&$affected, $withTracking, $withoutTracking, $changeRows) {
                // 변경이력 먼저 insert (bulk)
                if (!empty($changeRows)) {
                    foreach (array_chunk($changeRows, 1000) as $chunk) {
                        DB::table('order_change_logs')->insert($chunk);
                    }
                }

                $uniqueBy = ['channel_id', 'channel_order_no'];

                $commonUpdateCols = [
                    'product_id',
                    'product_title', 'option_title', 'quantity',
                    'buyer_name', 'buyer_phone', 'buyer_postcode', 'buyer_addr_full', 'buyer_addr1', 'buyer_addr2',
                    'receiver_name', 'receiver_phone', 'receiver_postcode', 'receiver_addr_full', 'receiver_addr1', 'receiver_addr2',
                    'shipping_request', 'customer_note', 'admin_memo',
                    'ordered_at', 'status_src', 'status_std',
                    'raw_payload', 'raw_meta', 'raw_hash', 'updated_at',
                ];

                // (1) 송장번호 있는 건 tracking_no 포함 갱신
                if (!empty($withTracking)) {
                    $affected += DB::table('orders')->upsert(
                        $withTracking,
                        $uniqueBy,
                        array_merge($commonUpdateCols, ['tracking_no'])
                    );
                }

                // (2) 송장번호 없는 건 tracking_no 제외(기존 값 보존)
                if (!empty($withoutTracking)) {
                    $affected += DB::table('orders')->upsert(
                        $withoutTracking,
                        $uniqueBy,
                        $commonUpdateCols
                    );
                }
            });
        } catch (Throwable $e) {
            report($e);
            return ApiResponse::fail('server_error', '주문 반영 중 오류가 발생했습니다.', 500);
        }

        return ApiResponse::success([
            'stats' => [
                'received'          => $received,
                'valid'             => $valid,
                'invalid'           => $invalid,
                'affected'          => (int) $affected,
                'changes'           => count($changeRows),
                'ordered_at_filled' => (int) $orderedAtFilled,
                'upload_id'         => $uploadId,
            ],
            'failures' => $failures,
            'meta'     => $parsed['meta'] ?? [],
        ], '주문 DB 반영이 완료되었습니다.');
    }

    /**
     * 변경 이력 한 줄 추가(값이 실제로 바뀐 경우만)
     * ※ changed_at 컬럼 없음: created_at을 변경시각으로 사용
     *
     * 정책:
     * - 새 값이 빈값(null/'')이면 기록하지 않음 (기존 정책 유지)
     *   -> "비우기"를 변경으로 기록해야 한다면 이 정책만 바꾸면 됨
     */
    private function appendChangeRow(
        array &$rows,
        Order $order,
        string $uploadId,
        ?int $changedBy,
        string $field,
        $old,
        $new,
        string $source,
        $now
    ): void {
        if ($new === null || $new === '') return;

        $oldStr = $old === null ? '' : (string) $old;
        $newStr = (string) $new;

        if ($oldStr === $newStr) return;

        $rows[] = [
            'order_id'   => (int) $order->id,
            'upload_id'  => $uploadId,
            'source'     => $source,
            'field'      => $field,
            'old_value'  => $oldStr,
            'new_value'  => $newStr,
            'changed_by' => $changedBy,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /** 절대경로 판별 */
    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') return false;
        if (Str::startsWith($path, '/')) return true;                 // *nix
        if (preg_match('/^[A-Za-z]:\\\\/', $path) === 1) return true; // Windows (C:\)
        if (Str::startsWith($path, '\\\\')) return true;              // Windows UNC (\\server\share)
        return false;
    }
}
