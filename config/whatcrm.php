<?php

return [

    'token' => env('WHATCRM_WEBHOOK_TOKEN'),

    'send_message_url' => env(
        'WHATCRM_SEND_MESSAGE_URL',
        'https://web.airaccretion.com/api/v1/send-message'
    ),

    'send_message_token' =>
        env('WHATCRM_SEND_MESSAGE_TOKEN'),

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

    'openai_responses_url' =>
        env(
            'WHATCRM_OPENAI_RESPONSES_URL',
            'https://api.openai.com/v1/responses'
        ),

    'timeout' => 10,

];
