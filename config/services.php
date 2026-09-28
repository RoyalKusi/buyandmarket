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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // TDD §7.4: PaymentGateway's two current implementations
    // (App\Services\Payments). Credentials are placeholders in
    // .env.example — never committed real values (TDD §8.6).
    'pesepay' => [
        'integration_key' => env('PESEPAY_INTEGRATION_KEY'),
        'encryption_key' => env('PESEPAY_ENCRYPTION_KEY'),
        'base_url' => env('PESEPAY_BASE_URL', 'https://api.pesepay.com/api/payments-engine/v1'),
        'result_url' => env('PESEPAY_RESULT_URL'),
        'return_url' => env('PESEPAY_RETURN_URL'),
    ],

    'paynow' => [
        'integration_id' => env('PAYNOW_INTEGRATION_ID'),
        'integration_key' => env('PAYNOW_INTEGRATION_KEY'),
        'base_url' => env('PAYNOW_BASE_URL', 'https://www.paynow.co.zw/interface'),
        'result_url' => env('PAYNOW_RESULT_URL'),
        'return_url' => env('PAYNOW_RETURN_URL'),
    ],

];
