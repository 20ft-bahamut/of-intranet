<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Str;

class RequestId
{
    public function handle($request, Closure $next)
    {
        $rid = $request->headers->get('X-Request-Id') ?: Str::ulid()->toBase32();
        $request->attributes->set('request_id', $rid);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $rid);

        return $response;
    }
}
