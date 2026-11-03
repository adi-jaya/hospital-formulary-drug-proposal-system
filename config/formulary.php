<?php

declare(strict_types=1);

return [
    'doctor_api_url' => env('FORMULARY_DOCTOR_API_URL', 'https://portalsifast.rsaisyiyahsitifatimah.com/api/dokter'),
    'doctor_api_timeout' => (int) env('FORMULARY_DOCTOR_API_TIMEOUT', 10),
    'doctor_cache_ttl_hours' => (int) env('FORMULARY_DOCTOR_CACHE_TTL_HOURS', 12),
    'feed_token' => env('FORMULARY_FEED_TOKEN', ''),
];
