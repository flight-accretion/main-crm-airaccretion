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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Airpoints integration configuration. The URL may be provided via
    'airpoints' => [
        'base_url' => env('AIRPOINTS_API_URL', 'https://airpoints.airaccretion.com/'),
    ],
    'meta_whatsapp' => [
        'token' => env('META_WHATSAPP_TOKEN'),
        'phone_number_id' => env('META_WHATSAPP_PHONE_NUMBER_ID'),
    ],
    'msg91' => [
        'auth_key'            => env('MSG91_AUTH_KEY'),
        'whatsapp_integrated' => env('MSG91_WHATSAPP_INTEGRATED_NUMBER'),
    ],
    'whatscrm' => [
        'api_url'   => env('WHATSCRM_API_URL'),
        'api_token' => env('WHATSCRM_API_TOKEN'),
    ],

    // ═══════════════════════════════════════════════════════════════════════
    // VOUCHER WHATSCRM ACCOUNT (SEPARATE)
    // Different business account with different credentials
    // ═══════════════════════════════════════════════════════════════════════
    'whatscrm_vouchers' => [
        'api_url'           => env('WHATSCRM_VOUCHERS_API_URL'),
        'api_token'         => env('WHATSCRM_VOUCHERS_API_TOKEN'),
        'business_id'       => env('WHATSCRM_VOUCHERS_BUSINESS_ID'),
        'whatsapp_phone_id' => env('WHATSCRM_VOUCHERS_WHATSAPP_PHONE_ID'),
        'app_id'            => env('WHATSCRM_VOUCHERS_APP_ID'),
    ],

];
