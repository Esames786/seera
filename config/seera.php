<?php

return [
    'company_name' => env('SEERA_COMPANY_NAME', 'Seera Construction'),
    'admin' => [
        'name' => env('SEERA_ADMIN_NAME'),
        'email' => env('SEERA_ADMIN_EMAIL'),
        'username' => env('SEERA_ADMIN_USERNAME'),
        'password' => env('SEERA_ADMIN_PASSWORD'),
    ],
    'organization' => [
        // Domain used to build login emails for the organization chart accounts.
        'email_domain' => env('SEERA_ORG_EMAIL_DOMAIN', 'seera.local'),
    ],
    // Prefix per employee classification for automatically numbered employee codes (NR-03).
    'employee_codes' => [
        'Sponsorship' => env('SEERA_EMPLOYEE_CODE_SPONSORSHIP', 'SP-'),
        'Freelancer' => env('SEERA_EMPLOYEE_CODE_FREELANCER', 'FL-'),
    ],
    // First month of the financial year (1 = January) used by the report quick ranges (NR-21).
    'financial_year_start_month' => (int) env('SEERA_FINANCIAL_YEAR_START_MONTH', 1),
    // Offline Mobile Sync, Phase 1. Conservative defaults, documented in the user
    // guide; no approved business policy existed, so the owner may tune these by env.
    'offline_sync' => [
        // Queued actions per request (and per request with files).
        'batch_size' => (int) env('SEERA_OFFLINE_SYNC_BATCH_SIZE', 20),
        'batch_size_with_files' => (int) env('SEERA_OFFLINE_SYNC_BATCH_SIZE_WITH_FILES', 5),
        // A captured attendance event older than this at sync time is refused (permanent).
        'attendance_max_age_hours' => (int) env('SEERA_OFFLINE_ATTENDANCE_MAX_AGE_HOURS', 72),
        // A captured time this far ahead of the server clock is refused (permanent).
        'future_tolerance_minutes' => (int) env('SEERA_OFFLINE_FUTURE_TOLERANCE_MINUTES', 10),
        // Device clock drift beyond this at sync time sends attendance to Needs Attention.
        'clock_skew_attention_minutes' => (int) env('SEERA_OFFLINE_CLOCK_SKEW_ATTENTION_MINUTES', 30),
        // Site expense dates may not be older than this when captured offline.
        'site_expense_max_age_days' => (int) env('SEERA_OFFLINE_SITE_EXPENSE_MAX_AGE_DAYS', 60),
    ],
];
