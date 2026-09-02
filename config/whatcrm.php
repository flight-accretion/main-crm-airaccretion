<?php

return [

    'token' => env('WHATCRM_WEBHOOK_TOKEN'),

    'legacy_lead_api_enabled' =>
        env('WHATCRM_LEGACY_LEAD_API_ENABLED', true),

    'send_message_url' => env(
        'WHATCRM_SEND_MESSAGE_URL',
        'https://web.airaccretion.com/api/v1/send-message'
    ),

    'send_message_token' =>
        env('WHATCRM_SEND_MESSAGE_TOKEN'),

    'template_message_url' => env(
        'WHATCRM_TEMPLATE_MESSAGE_URL',
        env('WHATSCRM_API_URL')
    ),

    'template_message_token' => env(
        'WHATCRM_TEMPLATE_MESSAGE_TOKEN',
        env('WHATSCRM_API_TOKEN')
    ),

    'assignment_customer_message_enabled' =>
        env(
            'WHATCRM_ASSIGNMENT_CUSTOMER_MESSAGE_ENABLED',
            env('APP_ENV') !== 'testing'
        ),

    'assignment_customer_template' =>
        env(
            'WHATCRM_ASSIGNMENT_CUSTOMER_TEMPLATE',
            'lead_qualified'
        ),

    'default_country_code' =>
        env('WHATCRM_DEFAULT_COUNTRY_CODE', '91'),

    'assignment_webhook' =>
        env('WHATCRM_N8N_ASSIGNMENT_WEBHOOK'),

    'ai_queue_connection' =>
        env('WHATCRM_AI_QUEUE_CONNECTION', 'database'),

    'ai_queue' =>
        env('WHATCRM_AI_QUEUE', 'whatsapp-ai'),

    'ai_auto_dispatch' =>
        env('WHATCRM_AI_AUTO_DISPATCH', true),

    'ai_process_limit' =>
        (int) env('WHATCRM_AI_PROCESS_LIMIT', 25),

    'ai_scheduler_watch_seconds' =>
        (float) env('WHATCRM_AI_SCHEDULER_WATCH_SECONDS', 55),

    'ai_scheduler_sleep_seconds' =>
        (float) env('WHATCRM_AI_SCHEDULER_SLEEP_SECONDS', 0.5),

    'openai_responses_url' =>
        env(
            'WHATCRM_OPENAI_RESPONSES_URL',
            'https://api.openai.com/v1/responses'
        ),

    'pricing_sheet_csv_url' =>
        env('WHATCRM_PRICING_SHEET_CSV_URL'),

    'pricing_sheet_id' =>
        env(
            'WHATCRM_PRICING_SHEET_ID',
            '1Hx1yLloKA-duKC0AKi2V1hzNsr1BHDpB3RNdr3LOVns'
        ),

    'pricing_sheet_gid' =>
        env('WHATCRM_PRICING_SHEET_GID', '0'),

    'pricing_sheet_cache_ttl' =>
        (int) env('WHATCRM_PRICING_SHEET_CACHE_TTL', 86400),

    'pricing_sheet_max_rows' =>
        (int) env('WHATCRM_PRICING_SHEET_MAX_ROWS', 100),

    'availability_data' =>
        env('WHATCRM_AVAILABILITY_DATA'),

    'approved_selling_facts' =>
        array_filter(
            array_map(
                'trim',
                explode('|', env('WHATCRM_APPROVED_SELLING_FACTS', ''))
            )
        ),

    'product_links' =>
        json_decode(
            env('WHATCRM_PRODUCT_LINKS_JSON', '[]'),
            true
        ) ?: [],

    'timeout' => 10,

];
