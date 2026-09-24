<?php

return [
    'initial_template_name' => env('REVIEW_INITIAL_TEMPLATE_NAME'),
    'reminder_template_name' => env('REVIEW_REMINDER_TEMPLATE_NAME'),
    'template_language' => env('REVIEW_TEMPLATE_LANGUAGE', 'en'),
    'review_url' => env('REVIEW_PUBLIC_URL'),
    'max_reminders' => (int) env('REVIEW_MAX_REMINDERS', 5),
    'notify_operations' => (bool) env('REVIEW_NOTIFY_OPERATIONS', true),
    'operations_template_name' => env('REVIEW_OPERATIONS_TEMPLATE_NAME'),
    'operations_recipient_user_ids' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('REVIEW_OPERATIONS_RECIPIENT_USER_IDS', '')))
    )),
    'operations_recipient_numbers' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('REVIEW_OPERATIONS_RECIPIENT_NUMBERS', '')))
    )),
];
