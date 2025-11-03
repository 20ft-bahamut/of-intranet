<?php

namespace App\Support;

class ApiResponse
{
    public static function success($data = null, string $message = null, int $status = 200)
    {
        return response()->json([
            'ok'         => true,
            'data'       => $data,
            'message'    => $message,
            'request_id' => request()->attributes->get('request_id'),
        ], $status);
    }

    public static function fail(string $code, string $message, int $status, array $errors = null)
    {
        return response()->json([
            'ok'         => false,
            'status'     => $status,
            'code'       => $code,
            'message'    => $message,
            'errors'     => $errors,
            'request_id' => request()->attributes->get('request_id'),
        ], $status);
    }
}
