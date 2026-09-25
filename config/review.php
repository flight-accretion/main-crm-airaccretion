<?php

return [
    'initial_template_name' => env(
        'REVIEW_INITIAL_TEMPLATE_NAME',
        'feedback_template_1'
    ),
    'reminder_template_name' => env(
        'REVIEW_REMINDER_TEMPLATE_NAME',
        'feedback_template_1'
    ),
    'template_language' => env('REVIEW_TEMPLATE_LANGUAGE', 'en'),
    'review_url' => env('REVIEW_PUBLIC_URL'),
    'default_public_url' => env(
        'REVIEW_DEFAULT_PUBLIC_URL',
        env('REVIEW_PUBLIC_URL')
    ),
    'public_urls' => [
        'indore' => env('REVIEW_PUBLIC_URL_INDORE'),
        'mumbai' => env('REVIEW_PUBLIC_URL_MUMBAI'),
        'goa' => env('REVIEW_PUBLIC_URL_GOA'),
        'bengaluru' => env('REVIEW_PUBLIC_URL_BENGALURU'),
        'chennai' => env('REVIEW_PUBLIC_URL_CHENNAI'),
    ],
    'max_reminders' => (int) env('REVIEW_MAX_REMINDERS', 5),
    'notify_operations' => (bool) env('REVIEW_NOTIFY_OPERATIONS', true),
    'operations_template_name' => env(
        'REVIEW_OPERATIONS_TEMPLATE_NAME'
    ),
    'operations_recipient_user_ids' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('REVIEW_OPERATIONS_RECIPIENT_USER_IDS', '')))
    )),
    'operations_recipient_numbers' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('REVIEW_OPERATIONS_RECIPIENT_NUMBERS', '')))
    )),
];
