<?php

return [
    'activity_event_cutover_at' => env('KPI_ACTIVITY_EVENT_CUTOVER_AT'),

    'default_templates' => [
        'sales' => [
            'name' => 'Retail Sales KPI',
            'department_label' => 'Retail',
            'working_days_per_month' => 22,
            'metrics' => [
                [
                    'code' => 'daily_outreach',
                    'name' => 'Daily Outreach',
                    'description' => 'Make 50 calls to customers to generate leads.',
                    'weightage' => 25,
                    'measurement_type' => 'automatic',
                    'source_key' => 'sales_outreach',
                    'target_value' => 50,
                    'direction' => 'higher_better',
                    'score_rules' => [5 => 100, 4 => 90, 3 => 80, 2 => 70, 1 => 60],
                    'score_labels' => [5 => '100%', 4 => '90%', 3 => '80%', 2 => '70%', 1 => '<60%'],
                    'sort_order' => 10,
                ],
                [
                    'code' => 'lead_conversion',
                    'name' => 'Lead Conversion Rate',
                    'description' => 'Convert at least 30% of incoming leads into successful bookings.',
                    'weightage' => 15,
                    'measurement_type' => 'automatic',
                    'source_key' => 'sales_conversion',
                    'target_value' => 30,
                    'direction' => 'higher_better',
                    'score_rules' => [5 => 30, 4 => 25, 3 => 20, 2 => 15, 1 => 0],
                    'score_labels' => [5 => '30%', 4 => '25%', 3 => '20%', 2 => '15%', 1 => '<15%'],
                    'sort_order' => 20,
                ],
                [
                    'code' => 'monthly_target',
                    'name' => 'Achieve Monthly Target',
                    'description' => 'Achieve your monthly target as set out by manager.',
                    'weightage' => 40,
                    'measurement_type' => 'automatic',
                    'source_key' => 'sales_target',
                    'target_value' => null,
                    'direction' => 'higher_better',
                    'score_rules' => [5 => 100, 4 => 90, 3 => 80, 2 => 70, 1 => 60],
                    'score_labels' => [5 => '100%', 4 => '90%', 3 => '80%', 2 => '70%', 1 => '<60%'],
                    'sort_order' => 30,
                ],
                [
                    'code' => 'response_time',
                    'name' => 'Response Time',
                    'description' => 'Respond to client inquiries within the first 15 minutes.',
                    'weightage' => 3,
                    'measurement_type' => 'automatic',
                    'source_key' => 'sales_response_time',
                    'target_value' => 15,
                    'direction' => 'lower_better',
                    'score_rules' => [5 => 15, 4 => 20, 3 => 30, 2 => 60, 1 => 999],
                    'score_labels' => [5 => 'within 15 min', 4 => 'within 20 min', 3 => 'within 30 min', 2 => 'within 60 min', 1 => 'Above 60 min'],
                    'sort_order' => 40,
                ],
                [
                    'code' => 'followup_sla',
                    'name' => 'Follow-Up',
                    'description' => 'Ensure follow-up on pending cases within 4 hours.',
                    'weightage' => 2,
                    'measurement_type' => 'automatic',
                    'source_key' => 'sales_followup_sla',
                    'target_value' => 100,
                    'direction' => 'higher_better',
                    'score_rules' => [5 => 100, 4 => 90, 3 => 80, 2 => 70, 1 => 60],
                    'score_labels' => [5 => '100%', 4 => '90%', 3 => '80%', 2 => '70%', 1 => '<60%'],
                    'sort_order' => 50,
                ],
                [
                    'code' => 'payment_collection',
                    'name' => 'Payment Collection',
                    'description' => 'Collect payment and payment follow-ups of 100% customers.',
                    'weightage' => 5,
                    'measurement_type' => 'automatic',
                    'source_key' => 'sales_payment_collection',
                    'target_value' => 100,
                    'direction' => 'higher_better',
                    'score_rules' => [5 => 100, 4 => 90, 3 => 80, 2 => 70, 1 => 60],
                    'score_labels' => [5 => '100%', 4 => '90%', 3 => '80%', 2 => '70%', 1 => '<60%'],
                    'sort_order' => 60,
                ],
                [
                    'code' => 'attendance',
                    'name' => 'Attendance',
                    'description' => 'Achieve a punctuality rate of 95% for scheduled shifts.',
                    'weightage' => 10,
                    'measurement_type' => 'automatic',
                    'source_key' => 'sales_attendance',
                    'target_value' => 95,
                    'direction' => 'higher_better',
                    'score_rules' => [5 => 100, 4 => 90, 3 => 80, 2 => 70, 1 => 60],
                    'score_labels' => [5 => '100%', 4 => '90%', 3 => '80%', 2 => '70%', 1 => '<60%'],
                    'sort_order' => 70,
                ],
            ],
        ],
    ],

    'outreach' => [
        'standard_queue_size' => 50,
        'daily_standard_target' => 50,
        'extra_batch_size' => 50,
        'cooling_days' => 60,
        'outbound_ivr_codes' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('KPI_OUTREACH_OUTBOUND_IVR_CODES', ''))
        ))),
    ],

    'attendance' => [
        /*
         * Fallback policy used only when no active default policy has been
         * created yet during deployment.
         */
        'shift_start' => env('KPI_ATTENDANCE_SHIFT_START', '10:30'),
        'grace_minutes' => (int) env('KPI_ATTENDANCE_GRACE_MINUTES', 15),

        /*
         * These statuses do not count in the punctuality denominator.
         */
        'non_scheduled_statuses' => [
            'WO',
            'HLD',
        ],
    ],
];
