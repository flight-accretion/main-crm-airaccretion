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

    'timeout' => 10,

];
