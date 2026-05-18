<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'sso' => [
        'base_url' => env('SSO_BASE_URL', 'http://127.0.0.1:8000'),
        'user_url' => env('SSO_USER_URL'),
        'client_id' => env('SSO_CLIENT_ID'),
        'client_secret' => env('SSO_CLIENT_SECRET'),
        'redirect_uri' => env('SSO_REDIRECT_URI', env('APP_URL').'/auth/sso/callback'),
        'logout_redirect_uri' => env('SSO_LOGOUT_REDIRECT_URI'),
    ],

    'academic_api' => [
        'base_url' => env('ACADEMIC_API_BASE_URL', 'http://172.16.0.60/academic/api/v2'),
        'timeout' => env('ACADEMIC_API_TIMEOUT', 15),
        'connect_timeout' => env('ACADEMIC_API_CONNECT_TIMEOUT', 5),
    ],

    'report_server' => [
        'base_url' => env('REPORT_SERVER_BASE_URL', 'http://172.16.0.41/api/app'),
        'timeout' => env('REPORT_SERVER_TIMEOUT', 30),
        'connect_timeout' => env('REPORT_SERVER_CONNECT_TIMEOUT', 5),
    ],

    'evaluation_api' => [
        'base_url' => env('EVALUATION_API_BASE_URL', 'https://localapps.usm.edu.ph/api/app'),
        'timeout' => env('EVALUATION_API_TIMEOUT', 15),
        'connect_timeout' => env('EVALUATION_API_CONNECT_TIMEOUT', 5),
    ],

    'academic_jwt' => [
        'secret' => env('ACADEMIC_JWT_SECRET'),
        'algorithm' => env('ACADEMIC_JWT_ALGORITHM', 'HS256'),
    ],

    'mikrotik' => [
        'base_url' => env('MIKROTIK_API_BASE_URL'),
        'token' => env('MIKROTIK_API_TOKEN'),
        'host' => env('MIKROTIK_HOST'),
        'user' => env('MIKROTIK_USER'),
        'pass' => env('MIKROTIK_PASS'),
        'port' => env('MIKROTIK_PORT', 8728),
        'ssl' => env('MIKROTIK_SSL', false),
        'hotspot_server' => env('MIKROTIK_HOTSPOT_SERVER', 'hotspot2'),
        'hotspot_profile' => env('MIKROTIK_HOTSPOT_PROFILE', 'U-Students'),
        'timeout' => env('MIKROTIK_API_TIMEOUT', 15),
        'connect_timeout' => env('MIKROTIK_API_CONNECT_TIMEOUT', 5),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
