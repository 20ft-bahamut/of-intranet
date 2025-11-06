<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ProcessChannelExcel;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OrderUploadController extends Controller
{
    public function upload(Request $req, Channel $channel, ProcessChannelExcel $proc)
    {
        // 암호화 채널은 비밀번호 필수
        if ($channel->is_excel_encrypted && !$req->filled('password')) {
            return ApiResponse::fail(
                'validation_failed',
                '암호화된 파일은 비밀번호가 필요합니다.',
                422,
                ['password' => ['required']]
            );
        }

        // 파일 존재/유효성만 최소 확인 (확장자/MIME 검증 제거)
        $file = $req->file('file');
        if (!$file || !$file->isValid()) {
            return ApiResponse::fail(
                'validation_failed',
                '파일 업로드에 실패했습니다.',
                422,
                ['file' => ['upload_failed']]
            );
        }

        // 저장
        $disk = config('ofintranet.upload_disk');
        $root = trim(config('ofintranet.upload_root'), '/');
        $ext  = $file->getClientOriginalExtension() ?: 'dat';
        $filename = now()->format('Ymd_His') . '_' . uniqid() . '.' . $ext;
        $path = $file->storeAs($root . '/' . $channel->code, $filename, $disk);
        $abs  = Storage::disk($disk)->path($path);

        // 처리
        try {
            $result = $proc->handle($channel, $abs, $req->input('password'));
        } catch (\Throwable $e) {
            return ApiResponse::fail(
                'server_error',
                '업로드 처리 중 오류: ' . $e->getMessage(),
                500
            );
        }

        return ApiResponse::success([
            'preview' => $result['preview'],
            'count'   => count($result['rows']),
            'stored'  => $path,
        ], '업로드 처리 완료', 200);
    }
}
