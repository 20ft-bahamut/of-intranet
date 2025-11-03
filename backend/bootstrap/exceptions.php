<?php
use App\Support\ApiResponse;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;                 // ✅ 로그
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return function (Exceptions $exceptions): void {
    // (Throwable $e, Request $request) 시그니처 필수
    $exceptions->render(function (\Throwable $e, Request $request) {
        // /api/* 만 JSON 포맷 + 로깅
        if (! str_starts_with($request->path(), 'api/')) {
            return null;
        }

        // ✅ 공통 에러 로그 (항상 찍음)
        $rid = $request->attributes->get('request_id');  // RequestId 미들웨어 썼다면 존재
        $sqlState = null;
        if ($e instanceof QueryException) {
            $sqlState = $e->errorInfo[0] ?? null;
        } elseif ($e instanceof \PDOException) {
            $sqlState = $e->errorInfo[0] ?? (string) $e->getCode();
        }

        Log::error('[API EXCEPTION]', [
            'request_id' => $rid,
            'path'       => $request->path(),
            'exception'  => get_class($e),
            'message'    => $e->getMessage(),
            'sql_state'  => $sqlState,
            // 스택은 너무 길 수 있으니 필요시 주석 해제
            // 'trace'      => $e->getTraceAsString(),
        ]);

        // 422: 검증 실패
        if ($e instanceof ValidationException) {
            return ApiResponse::fail('validation_failed', '입력값을 확인하세요.', 422, $e->errors());
        }

        // 401/403/404/429
        if ($e instanceof AuthenticationException) {
            return ApiResponse::fail('unauthenticated', '인증이 필요합니다.', 401);
        }
        if ($e instanceof AuthorizationException) {
            return ApiResponse::fail('forbidden', '접근 권한이 없습니다.', 403);
        }
        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return ApiResponse::fail('not_found', '리소스를 찾을 수 없습니다.', 404);
        }
        if ($e instanceof TooManyRequestsHttpException) {
            return ApiResponse::fail('rate_limited', '잠시 후 다시 시도하세요.', 429);
        }

        // 409: 무결성/유니크 제약
        if ($e instanceof QueryException && (($e->errorInfo[0] ?? null) === '23000')) {
            return ApiResponse::fail('conflict', '데이터 충돌이 발생했습니다.', 409);
        }
        if ($e instanceof \PDOException) {
            $sqlState = $e->errorInfo[0] ?? (string) $e->getCode();
            if ($sqlState === '23000') {
                return ApiResponse::fail('conflict', '데이터 충돌이 발생했습니다.', 409);
            }
        }

        // 그 외 → 500
        return ApiResponse::fail('server_error', '서버 오류가 발생했습니다.', 500);
    });
};
