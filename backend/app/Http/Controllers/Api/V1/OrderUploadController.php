<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ProcessChannelExcel;
use App\Http\Controllers\Controller;
use App\Http\Requests\CommitChannelOrdersRequest;
use App\Models\Channel;
use App\Models\Order;
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
     * 업로드 → 미리보기
     */
    public function upload(Request $req, Channel $channel, ProcessChannelExcel $proc)
    {
        if ($channel->is_excel_encrypted && !$req->filled('password')) {
            return ApiResponse::fail('validation_failed', '암호화된 파일은 비밀번호가 필요합니다.', 422);
        }

        $file = $req->file('file');
        if (!$file || !$file->isValid()) {
            return ApiResponse::fail('validation_failed', '파일 업로드 실패', 422);
        }

        $disk = config('ofintranet.upload_disk', 'local');
        $root = trim(config('ofintranet.upload_root', 'uploads'), '/');

        $filename = now()->format('Ymd_His') . '_' . Str::uuid() . '.' . ($file->getClientOriginalExtension() ?: 'xlsx');
        $stored   = $file->storeAs($root.'/'.$channel->code, $filename, $disk);
        $absPath  = Storage::disk($disk)->path($stored);

        try {
            $parsed = $proc->handle($channel, $absPath, (string)$req->input('password', ''));
        } catch (Throwable $e) {
            report($e);
            return ApiResponse::fail('server_error', '엑셀 파싱 실패', 500);
        }

        return ApiResponse::success([
            'preview'     => $parsed['preview'] ?? [],
            'count'       => count($parsed['rows'] ?? []),
            'stored'      => $stored,
            'upload_path' => $absPath,
            'meta'        => $parsed['meta'] ?? null,
        ]);
    }

    /**
     * 전화번호 정규화
     */
    private function normalizePhone(?string $raw): ?string
    {
        if (!$raw) return null;

        $d = preg_replace('/\D+/', '', $raw);
        if ($d === '') return null;

        if (str_starts_with($d, '82')) {
            $d = '0'.substr($d, 2);
        }

        if (str_starts_with($d, '02')) {
            return strlen($d) === 9
                ? sprintf('02-%s-%s', substr($d,2,3), substr($d,5))
                : sprintf('02-%s-%s', substr($d,2,4), substr($d,6));
        }

        return strlen($d) === 10
            ? sprintf('%s-%s-%s', substr($d,0,3), substr($d,3,3), substr($d,6))
            : sprintf('%s-%s-%s', substr($d,0,3), substr($d,3,4), substr($d,7));
    }

    /**
     * 미리보기 → DB 반영 (변경이력 포함)
     */
    public function commit(CommitChannelOrdersRequest $req, Channel $channel, ProcessChannelExcel $proc)
    {
        $disk     = config('ofintranet.upload_disk', 'local');
        $rawPath  = (string)$req->input('upload_path');
        $password = (string)$req->input('password', '');

        $path = Str::startsWith($rawPath, '/')
            ? $rawPath
            : Storage::disk($disk)->path($rawPath);

        if (!File::exists($path)) {
            return ApiResponse::fail('not_found', '업로드 파일 없음', 404);
        }

        try {
            $parsed = $proc->handle($channel, $path, $password);
            $rows   = $parsed['rows'] ?? [];
        } catch (Throwable $e) {
            report($e);
            return ApiResponse::fail('server_error', '엑셀 재처리 실패', 500);
        }

        $now = now();
        $payload = [];
        $failures = [];

        foreach ($rows as $i => $r) {
            if (empty($r['channel_order_no']) || empty($r['receiver_name']) || empty($r['ordered_at'])) {
                $failures[] = ['row' => $i + 1, 'reason' => '필수값 누락'];
                continue;
            }

            // 기존 주문 조회 (변경 이력용)
            $existing = Order::where('channel_id', $channel->id)
                ->where('channel_order_no', $r['channel_order_no'])
                ->first();

            // 🔥 변경 이력 기록 (필드 화이트리스트)
            if ($existing) {
                $this->logChange($existing, 'tracking_no', $existing->tracking_no, $r['tracking_no'] ?? null);
                $this->logChange($existing, 'receiver_name', $existing->receiver_name, $r['receiver_name'] ?? null);
                $this->logChange($existing, 'receiver_phone', $existing->receiver_phone, $this->normalizePhone($r['receiver_phone'] ?? null));
                $this->logChange($existing, 'receiver_addr_full', $existing->receiver_addr_full, $r['receiver_addr_full'] ?? null);
            }

            $payload[] = [
                'channel_id'         => $channel->id,
                'channel_order_no'   => (string)$r['channel_order_no'],
                'product_id'         => $r['_product_id'] ?? null,

                'product_title'      => $r['product_title'] ?? null,
                'option_title'       => $r['option_title'] ?? null,
                'quantity'           => (int)($r['quantity'] ?? 1),
                'tracking_no'        => $r['tracking_no'] ?? null,

                'buyer_name'         => $r['buyer_name'] ?? null,
                'buyer_phone'        => $this->normalizePhone($r['buyer_phone'] ?? null),

                'receiver_name'      => $r['receiver_name'],
                'receiver_phone'     => $this->normalizePhone($r['receiver_phone'] ?? null),
                'receiver_postcode'  => $r['receiver_postcode'] ?? null,
                'receiver_addr_full' => $r['receiver_addr_full'] ?? null,

                'ordered_at'         => $r['ordered_at'],
                'status_src'         => $r['status_src'] ?? null,
                'status_std'         => $r['status_std'] ?? null,

                'raw_payload'        => json_encode($r['_raw'] ?? $r, JSON_UNESCAPED_UNICODE),
                'raw_hash'           => hash('sha256', json_encode($r['_raw'] ?? $r)),
                'created_at'         => $now,
                'updated_at'         => $now,
            ];
        }

        DB::transaction(function () use ($payload) {
            DB::table('orders')->upsert(
                $payload,
                ['channel_id', 'channel_order_no', 'product_id'],
                [
                    'product_title','option_title','quantity','tracking_no',
                    'buyer_name','buyer_phone',
                    'receiver_name','receiver_phone','receiver_postcode','receiver_addr_full',
                    'ordered_at','status_src','status_std',
                    'raw_payload','raw_hash','updated_at',
                ]
            );
        });

        return ApiResponse::success([
            'received' => count($rows),
            'saved'    => count($payload),
            'failed'   => count($failures),
            'failures' => $failures,
        ], '주문 반영 완료');
    }

    /**
     * 변경 이력 기록 (값이 실제로 바뀐 경우만)
     */
    private function logChange(Order $order, string $field, $old, $new): void
    {
        if ($new === null || $old === $new) return;

        DB::table('order_change_logs')->insert([
            'order_id'   => $order->id,
            'field'      => $field,
            'old_value'  => (string)$old,
            'new_value'  => (string)$new,
            'source'     => 'excel',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
