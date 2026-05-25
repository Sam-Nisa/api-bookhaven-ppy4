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

    /*
    |--------------------------------------------------------------------------
    | Google OAuth Configuration
    |--------------------------------------------------------------------------
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bakong Payment Gateway Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Bakong KHQR payment integration
    | Get your API token from: https://api-bakong.nbc.gov.kh/register
    |
    */

    'bakong' => [
        'api_token' => env('BAKONG_API_TOKEN'),
        'account_id' => env('BAKONG_ACCOUNT_ID', 'nisa_sam@bkrt'),
        'merchant_name' => env('BAKONG_MERCHANT_NAME', 'NISA SAM'),
        'merchant_city' => env('BAKONG_MERCHANT_CITY', 'Phnom Penh'),
        'merchant_id' => env('BAKONG_MERCHANT_ID'),
        'acquiring_bank' => env('BAKONG_ACQUIRING_BANK', 'ABA Bank'),
        'mobile_number' => env('BAKONG_MOBILE_NUMBER'),
    ],
    'imagekit' => [
    'public_key' => env('IMAGEKIT_PUBLIC_KEY'),
    'private_key' => env('IMAGEKIT_PRIVATE_KEY'),
    'url_endpoint' => env('IMAGEKIT_URL_ENDPOINT'),
],


];
