<?php

return [
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
];
