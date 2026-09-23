<?php

return [
    'driver' => env('EVENT_SEARCH_DRIVER', 'mysql'),
    'available' => filter_var(
        env('EVENT_SEARCH_AVAILABLE', true),
        FILTER_VALIDATE_BOOL
    ),
];
