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

    // Sinkron produk ke Google Merchant Center lewat Merchant API (sumber data jenis "Merchant API").
    // Kredensial = file JSON kunci service account; sinkron mati selama salah satu nilai belum diisi.
    'google_merchant' => [
        'account_id' => env('GOOGLE_MERCHANT_ACCOUNT_ID'),
        'data_source_id' => env('GOOGLE_MERCHANT_DATA_SOURCE_ID'),
        'credentials' => env('GOOGLE_MERCHANT_CREDENTIALS', storage_path('app/private/google-merchant.json')),
        'content_language' => env('GOOGLE_MERCHANT_CONTENT_LANGUAGE', 'id'),
        'feed_label' => env('GOOGLE_MERCHANT_FEED_LABEL', 'ID'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
