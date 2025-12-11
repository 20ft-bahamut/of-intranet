<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        // 운영용 프론트 도메인
        'http://ordermanager.orderfresh.co.kr',
        'https://ordermanager.orderfresh.co.kr',

        // 개발용 프론트 도메인
        'http://localhost:5173',
        'http://127.0.0.1:5173',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    /// 쿠키 인증(세션, sanctum 등) 절대 필요할 땐 true
    /// 지금은 fetchJson이 credentials: include 쓰고 있으므로 true가 맞다.
    'supports_credentials' => true,

];
