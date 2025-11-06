<?php

namespace App\Support;

class ApiResponse
{
    public static function success($data = null, string $message = null, int $status = 200, string $code = 'success')
    {
        return response()->json([
            'ok'         => true,
            'status'     => $status,
            'code'       => $code,
            'message'    => $message,
            'data'       => $data,
            'request_id' => request()->attributes->get('request_id'),
        ], $status);
    }

    public static function created($data = null, string $message = 'Created')
    {
        return self::success($data, $message, 201, 'created');
    }

    public static function conflict(string $message = 'Conflict', array $errors = null)
    {
        return self::fail('conflict', $message, 409, $errors);
    }

    public static function notFound(string $message = 'Not found')
    {
        return self::fail('not_found', $message, 404);
    }

    public static function forbidden(string $message = 'Forbidden')
    {
        return self::fail('forbidden', $message, 403);
    }

    public static function fail(
        string $code,
        string $message,
        int $status,
        array $errors = null
    ) {
        return response()->json([
            'ok'         => false,
            'status'     => $status,
            'code'       => $code,
            'message'    => $message,
            'errors'     => $errors,
            'request_id' => request()->attributes->get('request_id'),
        ], $status);
    }

    // 편의용: 422 Validation
    public static function validation(array $errors, string $message = 'Validation failed')
    {
        return self::fail('validation_failed', $message, 422, $errors);
    }

    // 편의용: 500
    public static function serverError(string $message = 'Server error', array $errors = null)
    {
        return self::fail('server_error', $message, 500, $errors);
    }
}
