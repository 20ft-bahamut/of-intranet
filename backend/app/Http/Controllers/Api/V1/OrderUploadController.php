<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ProcessChannelExcel;
use App\Http\Controllers\Controller;
use App\Http\Requests\CommitChannelOrdersRequest;
use App\Models\Channel;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class OrderUploadController extends Controller
{
    /**
     * 업로드 → 미리보기 생성
     * 응답: stored(상대경로), upload_path(절대경로) 모두 제공
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
        $ext   = $file->getClientOriginalExtension();
        $ext   = $ext ? $ext : 'xlsx';
        $uuid  = (string) Str::uuid();
        $stamp = now()->format('Ymd_His');
        $filename = $stamp . '_' . $uuid . '.' . $ext;

        // 저장 (상대경로: {root}/{channel_code}/{filename})
        $stored = $file->storeAs($root . '/' . $channel->code, $filename, $disk);
        $abs    = Storage::disk($disk)->path($stored);

        try {
            // 미리보기 생성 (절대경로로 처리)
            $password = (string) $req->input('password', '');
            $parsed   = $proc->handle($channel, $abs, $password);
        } catch (Throwable $e) {
            report($e);
            return ApiResponse::fail('server_error', '업로드 처리 중 오류가 발생했습니다.', 500);
        }

        return ApiResponse::success([
            'preview'     => $parsed['preview'] ?? [],
            'count'       => isset($parsed['rows']) ? count($parsed['rows']) : (count($parsed['preview'] ?? [])),
            'stored'      => $stored,   // 상대 경로(Storage 기준)
            'upload_path' => $abs,      // 절대 경로(커밋에서 바로 사용)
            'stats'       => $parsed['stats'] ?? null,
            'meta'        => $parsed['meta'] ?? null,
        ], '업로드 처리 완료');
    }

    private function normalizePhone(?string $raw): ?string
    {
        if ($raw === null) return null;

        // 숫자만 남김(국제전화 기호 등 제거)
        $d = preg_replace('/\D+/', '', $raw ?? '');
        if ($d === '') return null;

        // 82로 시작하면 국내 국번으로 환원 (예: 821012345678 -> 01012345678)
        if (str_starts_with($d, '82')) {
            $d = '0' . substr($d, 2);
        }

        // 02 지역번호(서울) 처리: 9~10자리
        if (str_starts_with($d, '02')) {
            if (strlen($d) === 9)  return sprintf('02-%s-%s', substr($d, 2, 3), substr($d, 5, 4));   // 02-XXX-XXXX
            if (strlen($d) === 10) return sprintf('02-%s-%s', substr($d, 2, 4), substr($d, 6, 4));  // 02-XXXX-XXXX
            // 길이가 이상하면 원본 유지
            return $raw;
        }

        // 휴대폰/일반 지역번호(3자리 국번): 10~11자리
        // 10자리: 3-3-4 (예: 010-123-4567 / 031-123-4567)
        if (strlen($d) === 10) {
            return sprintf('%s-%s-%s', substr($d, 0, 3), substr($d, 3, 3), substr($d, 6, 4));
        }
        // 11자리: 3-4-4 (예: 010-1234-5678)
        if (strlen($d) === 11) {
            return sprintf('%s-%s-%s', substr($d, 0, 3), substr($d, 3, 4), substr($d, 7, 4));
        }

        // 그 외: 길이 가변 → 안전하게 원본 그대로 반환
        return $raw;
    }


    /**
     * 미리보기 후 → DB 반영(커밋)
     *
     * 필수 컬럼(없으면 삽입 금지):
     * - (product_id는 선택: 매칭 실패 시 NULL 허용)
     * - receiver_name, receiver_postcode, receiver_addr_full, receiver_phone
     * - ordered_at
     * - raw_payload(또는 _cells)  ← 엑셀 "원본 행"이 반드시 존재해야 함
     *
     * 응답: stats { received, valid, invalid, affected }, failures[ { index, channel_order_no, reasons[] } ]
     * 갱신 정책: tracking_no 없는 재업로드는 기존 tracking_no를 덮지 않음(2단계 upsert).
     */
    public function commit(CommitChannelOrdersRequest $req, Channel $channel, ProcessChannelExcel $proc)
    {
        $disk     = (string) config('ofintranet.upload_disk', 'local');
        $rawPath  = (string) $req->input('upload_path', '');
        $password = (string) $req->input('password', '');

        // 경로 해석: 절대경로 우선, 아니면 Storage 기준 상대경로 → 절대경로
        $path = $rawPath;
        if (!$this->isAbsolutePath($path)) {
            $candidate = Storage::disk($disk)->path($path);
            if (File::exists($candidate)) {
                $path = $candidate;
            }
        }

        if (!File::exists($path)) {
            \Log::warning('orders.commit not_found', ['raw' => $rawPath, 'resolved' => $path, 'disk' => $disk]);
            return ApiResponse::fail('not_found', '업로드 파일을 찾을 수 없습니다.', 404);
        }

        try {
            // 동일 파이프라인으로 재파싱
            $parsed = $proc->handle($channel, $path, $password);
            $rows   = $parsed['rows'] ?? [];

            if (empty($rows)) {
                return ApiResponse::fail('validation_failed', '유효한 주문 행이 없습니다.', 422, [
                    'rows' => ['empty'],
                ]);
            }

            $now = now();
            $validPayload = [];
            $failures = [];
            $idx = 0;

            // 디버그: 실패 사유 집계
            $reasonAgg = [];

            foreach ($rows as $r) {
                $idx++;

                // (A) product_id 정규화 (_product_id 우선)
                $pid = $r['_product_id'] ?? ($r['product_id'] ?? null);
                if ($pid !== null && $pid !== '') {
                    if (is_numeric($pid)) $pid = (int) $pid;
                }

                // (B) 엑셀 "원본 행" 강제: raw_payload/_cells/_raw 중 하나는 필수
                $rawSourceKey = null;
                foreach (['raw_payload','_cells','_raw'] as $k) {
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
                $rawSource = $r[$rawSourceKey];

                // (C) 필수값 체크 (product_id는 선택)
                $reasons = [];
                if (empty($r['receiver_name']))              $reasons[] = 'receiver_name 누락';
                if (empty($r['receiver_postcode']))          $reasons[] = 'receiver_postcode 누락';
                if (empty($r['receiver_addr_full']))         $reasons[] = 'receiver_addr_full 누락';
                if (empty($r['receiver_phone']))             $reasons[] = 'receiver_phone 누락';
                if (empty($r['ordered_at']))                 $reasons[] = 'ordered_at 누락';

                if (!empty($reasons)) {
                    foreach ($reasons as $rr) { $reasonAgg[$rr] = ($reasonAgg[$rr] ?? 0) + 1; }
                    $failures[] = [
                        'index'            => $r['_row'] ?? $idx,
                        'channel_order_no' => $r['channel_order_no'] ?? null,
                        'reasons'          => $reasons,
                    ];
                    continue;
                }

                // (D) 원본 JSON/해시/meta (정규화 정보 포함 금지)
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

                // (E) upsert payload
                $validPayload[] = [
                    'channel_id'         => $channel->id,
                    'channel_order_no'   => (string) ($r['channel_order_no'] ?? ''),
                    'product_id'         => ($pid === '' ? null : $pid),

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

                    'shipping_request'   => $r['shipping_request'] ?? null,
                    'customer_note'      => $r['customer_note'] ?? null,
                    'admin_memo'         => $r['admin_memo'] ?? null,

                    'ordered_at'         => $r['ordered_at'] ?? null,
                    'status_src'         => $r['status_src'] ?? null,
                    'status_std'         => $r['status_std'] ?? null,

                    'raw_payload'        => $rawPayloadJson,
                    'raw_meta'           => $rawMetaJson,
                    'raw_hash'           => $rawHash,

                    'created_at'         => $now,
                    'updated_at'         => $now,
                ];
            }

            // 유효/무효 카운트 계산
            $received = count($rows);
            $valid    = count($validPayload);
            $invalid  = count($failures);

            // 디버그: 실패 사유 요약
            arsort($reasonAgg);
            \Log::info('orders.commit validation summary', [
                'received' => $received,
                'valid'    => $valid,
                'invalid'  => $invalid,
                'top_reasons' => array_slice($reasonAgg, 0, 5, true),
            ]);

            if ($valid === 0) {
                return ApiResponse::fail('validation_failed', '모든 행이 검증에서 탈락했습니다.', 422, [
                    'failures' => $failures,
                    'summary'  => $reasonAgg,
                ]);
            }

            // ===== 트랜잭션 + UPSERT (2단계: tracking_no 보호) =====
            $withTracking    = [];
            $withoutTracking = [];
            foreach ($validPayload as $row) {
                $hasTracking = isset($row['tracking_no']) && $row['tracking_no'] !== null && $row['tracking_no'] !== '';
                if ($hasTracking) $withTracking[] = $row;
                else              $withoutTracking[] = $row;
            }

            $affected = 0;
            DB::transaction(function () use ($withTracking, $withoutTracking, &$affected) {
                // (1) 송장번호 있는 건 전필드 갱신
                if (!empty($withTracking)) {
                    $affected += DB::table('orders')->upsert(
                        $withTracking,
                        ['channel_id', 'channel_order_no', 'product_id'],
                        [
                            'product_title','option_title','quantity','tracking_no',
                            'buyer_name','buyer_phone','buyer_postcode','buyer_addr_full','buyer_addr1','buyer_addr2',
                            'receiver_name','receiver_phone','receiver_postcode','receiver_addr_full','receiver_addr1','receiver_addr2',
                            'shipping_request','customer_note','admin_memo',
                            'ordered_at','status_src','status_std',
                            'raw_payload','raw_meta','raw_hash','updated_at',
                        ]
                    );
                }

                // (2) 송장번호 없는 건 tracking_no 제외(기존 값 보존)
                if (!empty($withoutTracking)) {
                    $affected += DB::table('orders')->upsert(
                        $withoutTracking,
                        ['channel_id', 'channel_order_no', 'product_id'],
                        [
                            'product_title','option_title','quantity',
                            // 'tracking_no' 제외
                            'buyer_name','buyer_phone','buyer_postcode','buyer_addr_full','buyer_addr1','buyer_addr2',
                            'receiver_name','receiver_phone','receiver_postcode','receiver_addr_full','receiver_addr1','receiver_addr2',
                            'shipping_request','customer_note','admin_memo',
                            'ordered_at','status_src','status_std',
                            'raw_payload','raw_meta','raw_hash','updated_at',
                        ]
                    );
                }
            });

            return ApiResponse::success([
                'stats' => [
                    'received' => $received,
                    'valid'    => $valid,
                    'invalid'  => $invalid,
                    'affected' => (int) $affected,
                ],
                'failures' => $failures,
                'meta'     => $parsed['meta'] ?? [],
            ], '주문 DB 반영이 완료되었습니다.');

        } catch (Throwable $e) {
            report($e);
            return ApiResponse::fail('server_error', '주문 반영 중 오류가 발생했습니다.', 500);
        }
    }

    /** 절대경로 판별 */
    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') return false;
        // *nix
        if (Str::startsWith($path, '/')) return true;
        // Windows (C:\)
        if (preg_match('/^[A-Za-z]:\\\\/', $path) === 1) return true;
        // Windows UNC (\\server\share)
        if (Str::startsWith($path, '\\\\')) return true;
        return false;
    }
}
