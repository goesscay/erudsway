<?php

return [

    /*
    | Course languages instructors can choose and learners can filter by.
    */
    'languages' => ['English', 'Hindi', 'Tamil', 'Telugu', 'Kannada', 'Malayalam', 'Marathi', 'Bengali', 'Gujarati'],

    /*
    | Thresholds for the badges shown on course cards. Tune as the catalogue grows.
    */
    'badges' => [
        'bestseller_min_enrollments' => (int) env('CATALOG_BESTSELLER_MIN', 25000),
        'highest_rated_min_rating' => 4.7,
        'highest_rated_min_reviews' => 50,
        'new_within_days' => 45,
    ],

    /*
    | Duration filter buckets, in hours: [min, max) — null means open-ended.
    */
    'duration_buckets' => [
        'short' => [0, 3],
        'medium' => [3, 6],
        'long' => [6, 17],
        'extra_long' => [17, null],
    ],

    /*
    | Browser cache lifetime (seconds) for public catalogue responses.
    */
    'public_cache_seconds' => (int) env('CATALOG_PUBLIC_CACHE', 60),
];
