
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
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

    /*
    |--------------------------------------------------------------------------
    | NIN Management API
    |--------------------------------------------------------------------------
    */

    'nin' => [
        'api_key' => env('NIN_API_KEY'),
        'base_url' => env('NIN_BASE_URL', env('APP_URL')),
        'timeout' => (int) env('NIN_TIMEOUT', 15),
        // Anwani ambayo simu inaweza kuifikia (mf. http://192.168.1.20:8000) kwa QR ya kadi.
        'card_verify_url' => env('CARD_VERIFY_URL'),
    ],

];
