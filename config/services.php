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

    'd365' => [
        'tenant_id' => env('D365_TENANT_ID'),
        'client_id' => env('D365_CLIENT_ID'),
        'client_secret' => env('D365_CLIENT_SECRET'),
        'resource' => env('D365_RESOURCE'),
    ],

    'rpa' => [
        // Shared secret checked by RpaFinalizeController — automaton/pw_service/
        // debit_note calls back into this app once it knows the real Document
        // No/Invoice Date, so the debit-note page can be genuinely re-rendered
        // via Blade instead of overlaid onto the existing PDF after the fact.
        'finalize_token' => env('RPA_FINALIZE_TOKEN'),
    ],

    // Internal WhatsApp channel bot (~/channel on this box, whatsapp-web.js).
    // Reached via the Docker bridge gateway since the bot runs on the host
    // via pm2, not in this app's container network.
    'whatsapp' => [
        'base_url' => env('WA_API_BASE', 'http://172.17.0.1:3000'),
        'api_key' => env('WA_API_KEY'),
    ],

];
