<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API authentication token
    |--------------------------------------------------------------------------
    */

    'token' => env(
        'CALL_SUMMARY_API_TOKEN'
    ),


    /*
    |--------------------------------------------------------------------------
    | IVR matching window
    |--------------------------------------------------------------------------
    |
    | Look +/- this many minutes around
    | incoming call_start_at.
    |
    */

    'ivr_match_window_minutes' =>
        (int) env(
            'CALL_SUMMARY_MATCH_WINDOW_MINUTES',
            10
        ),


    /*
    |--------------------------------------------------------------------------
    | Automatic matching threshold
    |--------------------------------------------------------------------------
    */

    'minimum_match_score' =>
        (int) env(
            'CALL_SUMMARY_MIN_MATCH_SCORE',
            70
        ),


    /*
    |--------------------------------------------------------------------------
    | Maximum automatic retries
    |--------------------------------------------------------------------------
    */

    'max_attempts' =>
        (int) env(
            'CALL_SUMMARY_MAX_ATTEMPTS',
            360
        ),
];