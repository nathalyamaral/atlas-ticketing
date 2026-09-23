<?php

return [
    'confirmation_failure' => [
        'window_minutes' => (int) env('CONFIRMATION_FAILURE_WINDOW_MINUTES', 5),
        'minimum_attempts' => (int) env('CONFIRMATION_FAILURE_MIN_ATTEMPTS', 10),
        'threshold_percent' => (float) env('CONFIRMATION_FAILURE_THRESHOLD_PERCENT', 20),
    ],
];
