<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'driver' => env('GOOGLE_DRIVER', 'demo'),
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
    ],

    'demo' => [
        'login_enabled' => (bool) env('DEMO_LOGIN_ENABLED', true),
    ],

    'llm' => [
        'base_url' => env('LLM_BASE_URL', 'https://openrouter.ai/api/v1'),
        'api_key' => env('LLM_API_KEY'),
        'model' => env('LLM_MODEL', 'google/gemma-4-31b-it:free'),
    ],

    'cron' => [
        'secret' => env('CRON_SECRET'),
    ],

    'frontend_url' => rtrim(explode(',', (string) env('FRONTEND_URL', 'http://localhost:5472'))[0], '/'),

];
