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
    'lead_api' => [
        'key' => env('LEAD_API_KEY'),
    ],

    'website_lead_webhook' => [
        'token' =>
            env(
                'WEBSITE_LEAD_WEBHOOK_TOKEN'
            ),
    ],

    'meta_whatsapp' => [
        'token' => env('META_WHATSAPP_TOKEN'),
        'phone_number_id' => env('META_WHATSAPP_PHONE_NUMBER_ID'),
    ],

  'google_drive_review' => [

    'enabled' =>
        env(
            'GOOGLE_DRIVE_REVIEW_ENABLED',
            false
        ),

    'client_id' =>
        env(
            'GOOGLE_DRIVE_CLIENT_ID'
        ),

    'client_secret' =>
        env(
            'GOOGLE_DRIVE_CLIENT_SECRET'
        ),

    'refresh_token' =>
        env(
            'GOOGLE_DRIVE_REFRESH_TOKEN'
        ),

    /*
     * Optional.
     *
     * This does NOT authorize private Drive access.
     * OAuth credentials above do that.
     */
    'api_key' =>
        env(
            'GOOGLE_DRIVE_API_KEY'
        ),

    'folder_id' =>
        env(
            'GOOGLE_DRIVE_REVIEW_FOLDER_ID'
        ),
],

    'msg91' => [
        'auth_key'            => env('MSG91_AUTH_KEY'),
        'whatsapp_integrated' => env('MSG91_WHATSAPP_INTEGRATED_NUMBER'),
        'booking_email_template' => env('MSG91_BOOKING_EMAIL_TEMPLATE', 'extra_service_template'),
        'email_domain'        => env('MSG91_EMAIL_DOMAIN', 'accretion.in'),
        'email_from_address'  => env('MSG91_EMAIL_FROM_ADDRESS', 'confirm@accretionaviation.com'),
        'email_from_name'     => env('MSG91_EMAIL_FROM_NAME', 'Accretion Aviation'),
    ],
    'whatscrm' => [
        'api_url'                   => env('WHATSCRM_API_URL'),
        'api_token'                 => env('WHATSCRM_API_TOKEN'),
        'booking_whatsapp_template' => env('WHATSCRM_BOOKING_WHATSAPP_TEMPLATE', 'extra_service_template'),
        'vendor_refund_template'    => env('WHATSCRM_VENDOR_REFUND_TEMPLATE', 'refund_vendor_notify_v2'),
        'vendor_refund_image_template' => env('WHATSCRM_VENDOR_REFUND_IMAGE_TEMPLATE', 'refund_vendor_notify_v2_img'),
    ],

    'skyrack' => [
        'leads_api_url' => env('SKYRACK_LEADS_API_URL', 'https://call.skyrack.ai/api/v1/leads'),
        'leads_api_token' => env('SKYRACK_LEADS_API_TOKEN'),
        'enabled' => env('SKYRACK_LEADS_API_ENABLED', false),
        'timeout' => env('SKYRACK_LEADS_API_TIMEOUT', 10),
        'backfill_limit' => env('SKYRACK_LEADS_BACKFILL_LIMIT', 1000),
        'token' => env('SKYRACK_LEADS_API_TOKEN'),
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

     'vi_cpaas' => [
        'auth_url' => env('VI_CPAAS_AUTH_URL'),
        'report_url' => env('VI_CPAAS_REPORT_URL'),
        'username' => env('VI_CPAAS_USERNAME'),
        'password' => env('VI_CPAAS_PASSWORD'),
        'dni' => env('VI_CPAAS_DNI'),
        'campaign_id' => env('VI_CPAAS_CAMPAIGN_ID'),
        'timeout' => env('VI_CPAAS_TIMEOUT', 60),
    ],

    'email_leads' => [
    'host' => env('EMAIL_LEADS_IMAP_HOST'),
    'port' => env('EMAIL_LEADS_IMAP_PORT', 993),

    'encryption' => env(
        'EMAIL_LEADS_IMAP_ENCRYPTION',
        'ssl'
    ),

    'validate_cert' => env(
        'EMAIL_LEADS_IMAP_VALIDATE_CERT',
        true
    ),

    'username' => env(
        'EMAIL_LEADS_IMAP_USERNAME'
    ),

    'password' => env(
        'EMAIL_LEADS_IMAP_PASSWORD'
    ),

    'mailbox' => env(
        'EMAIL_LEADS_IMAP_MAILBOX',
        'INBOX'
    ),

    'allowed_sender' => env(
        'EMAIL_LEADS_ALLOWED_SENDER',
        ''
    ),

    'recipient' => env(
        'EMAIL_LEADS_RECIPIENT',
        'leads@accretionaviation.com'
    ),

    ],

    'website_catalog' => [

        'webhook_secret' =>
            env(
                'WEBSITE_CATALOG_WEBHOOK_SECRET'
            ),

        'notes_url' =>
            env(
                'WEBSITE_CATALOG_NOTES_URL'
            ),

        'catalog_url' =>
            env(
                'WEBSITE_CATALOG_DATA_URL'
            ),

        'notes_secret' =>
            env(
                'WEBSITE_CATALOG_NOTES_SECRET'
            ),

    ],

    'booking_bank' => [
    'account_name' => env('BOOKING_BANK_ACCOUNT_NAME'),
    'bank_name' => env('BOOKING_BANK_NAME'),
    'account_number' => env('BOOKING_BANK_ACCOUNT_NUMBER'),
    'ifsc' => env('BOOKING_BANK_IFSC'),
    'branch' => env('BOOKING_BANK_BRANCH'),
],

    'booking_whatsapp' => [
        'enabled' => env('BOOKING_CONFIRMATION_WHATSAPP_ENABLED', true),
        'company_number' => env('BOOKING_CONFIRMATION_WHATSAPP_COMPANY_NUMBER', '+91 95753 40786'),
    ],

'google' => [

    'client_id' =>
        env(
            'GOOGLE_LOGIN_CLIENT_ID'
        ),

    'client_secret' =>
        env(
            'GOOGLE_LOGIN_CLIENT_SECRET'
        ),

    'redirect' =>
        env(
            'GOOGLE_LOGIN_REDIRECT_URI'
        ),
],


];
