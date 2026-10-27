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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI'),
    ],

    // Read through config() so the values survive `php artisan config:cache`.
    'payment' => [
        // Default gateway: "myfatoorah" or "moyasar". Any gateway can still be picked per payment.
        'provider' => env('PAYMENT_PROVIDER', 'myfatoorah'),
    ],

    'myfatoorah' => [
        'api_key' => env('MYFATOORAH_API_KEY'),
        'test_mode' => filter_var(env('MYFATOORAH_TEST_MODE', true), FILTER_VALIDATE_BOOLEAN),
        'country_iso' => env('MYFATOORAH_COUNTRY_ISO', 'SAU'),
        'currency' => env('MYFATOORAH_CURRENCY', 'SAR'),
    ],

    'moyasar' => [
        // Secret key: sk_test_... in test mode, sk_live_... in production.
        'secret_key' => env('MOYASAR_SECRET_KEY'),
        'base_url' => env('MOYASAR_BASE_URL', 'https://api.moyasar.com/v1'),
        'timeout' => (int) env('MOYASAR_TIMEOUT', 20),
    ],

    'sms' => [
        'provider' => env('SMS_PROVIDER', 'twilio'),
    ],

    'oursms' => [
        'token' => env('OURSMS_TOKEN'),
        'sender' => env('OURSMS_SENDER'),
    ],

    // One platform bot for every parent: a parent presses Start and shares their phone number.
    'telegram' => [
        'token' => env('TELEGRAM_BOT_TOKEN'),
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        // false: the platform bot only manages teachers' own bots; parents are linked and messaged through
        // each teacher's own bot. true brings back the shared parents bot for teachers without one.
        'shared_parents_bot' => (bool) env('TELEGRAM_SHARED_PARENTS_BOT', false),
        // The manager bot ("Bot Management Mode" in BotFather) that creates each teacher's own bot. Unset, it is the shared platform bot itself, which then serves both jobs on one webhook.
        'manager_token' => env('TELEGRAM_MANAGER_BOT_TOKEN') ?: env('TELEGRAM_BOT_TOKEN'),
        'manager_username' => env('TELEGRAM_MANAGER_BOT_USERNAME') ?: env('TELEGRAM_BOT_USERNAME'),
        'manager_webhook_secret' => env('TELEGRAM_MANAGER_WEBHOOK_SECRET') ?: env('TELEGRAM_WEBHOOK_SECRET'),
    ],

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'phone' => env('TWILIO_PHONE'),
    ],

];
