<?php

return [
    'debug_flow_logs' => env('CRM_DEBUG_FLOW_LOGS', false),
    'max_report_rows' => (int) env('CRM_MAX_REPORT_ROWS', 500),
    'master_cache_ttl' => (int) env('CRM_MASTER_CACHE_TTL', 900),
    'debug_logs' => filter_var(env('CRM_DEBUG_LOGS', false), FILTER_VALIDATE_BOOLEAN),
    'queue_warning_jobs' => (int) env('CRM_QUEUE_WARNING_JOBS', 1000),
    'failed_jobs_warning' => (int) env('CRM_FAILED_JOBS_WARNING', 50),
    'chat_page_size' => (int) env('CRM_CHAT_PAGE_SIZE', 50),
    'whatsapp_history_retention_months' => (int) env('CRM_WHATSAPP_HISTORY_RETENTION_MONTHS', 6),
    'google_chat_event_retention_months' => (int) env('CRM_GOOGLE_CHAT_EVENT_RETENTION_MONTHS', 2),
    'whatsapp_ai_batch_retention_days' => (int) env('CRM_WHATSAPP_AI_BATCH_RETENTION_DAYS', 60),
    'email_lead_log_retention_days' => (int) env('CRM_EMAIL_LEAD_LOG_RETENTION_DAYS', 90),
    'reminder_log_retention_days' => (int) env('CRM_REMINDER_LOG_RETENTION_DAYS', 180),
    'google_chat_retry_max_age_days' => (int) env('CRM_GOOGLE_CHAT_RETRY_MAX_AGE_DAYS', 7),
    'custom_log_retention_days' => (int) env('CRM_CUSTOM_LOG_RETENTION_DAYS', 14),
    'temp_file_retention_days' => (int) env('CRM_TEMP_FILE_RETENTION_DAYS', 7),
    'disk_warning_percent' => (int) env('CRM_DISK_WARNING_PERCENT', 85),
];
