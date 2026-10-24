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

    // The GST simulator's integration API (simulator: php artisan integration:key-create … --scopes=users,sso).
    'simulator' => [
        'url' => env('SIMULATOR_URL', 'https://simulator.erudsway.com'),
        'key' => env('SIMULATOR_API_KEY'),
        // The free trial anyone can start from the GST Simulator page, and the course that sells
        // simulator-only plans (created by `php artisan simulator:setup-plans`).
        'trial_days' => (int) env('SIMULATOR_TRIAL_DAYS', 7),
        'plans_course' => env('SIMULATOR_PLANS_COURSE', 'gst-simulator'),
    ],

    // Google Analytics 4. Both values can be changed by an administrator under Admin > Settings,
    // which overrides these defaults; they are off until a Measurement ID is set and switched on.
    'google_analytics' => [
        'enabled' => (bool) env('GOOGLE_ANALYTICS_ENABLED', false),
        'measurement_id' => env('GOOGLE_ANALYTICS_ID', ''),
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

];
