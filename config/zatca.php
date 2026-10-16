<?php

/*
 * ZATCA e-invoicing, Phase 2 (Integration Phase).
 *
 * Environments follow ZATCA's own terminology (FATOORA Portal User Manual v3,
 * May 2023, section 3 "API endpoints"): the Developer Portal integration
 * sandbox, the FATOORA simulation portal, and production (core). The
 * environment is explicit configuration and is never inferred from APP_ENV.
 * Production is disabled by default and needs two deliberate settings.
 */
return [
    // sandbox | simulation | production
    'environment' => env('ZATCA_ENVIRONMENT', 'sandbox'),

    // Production calls are refused unless this is true AND environment=production.
    'production_enabled' => (bool) env('ZATCA_PRODUCTION_ENABLED', false),

    'endpoints' => [
        // Developer Portal integration sandbox. The official base path is published in the
        // Swagger files behind the Developer Portal login; confirm it there before first use.
        'sandbox' => env('ZATCA_SANDBOX_BASE_URL', 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal'),
        // FATOORA Portal User Manual v3: simulation endpoints.
        'simulation' => env('ZATCA_SIMULATION_BASE_URL', 'https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation'),
        // FATOORA Portal User Manual v3: production (core) endpoints.
        'production' => env('ZATCA_PRODUCTION_BASE_URL', 'https://gw-fatoora.zatca.gov.sa/e-invoicing/core'),
    ],

    'http' => [
        'timeout_seconds' => (int) env('ZATCA_HTTP_TIMEOUT', 30),
        'connect_timeout_seconds' => (int) env('ZATCA_HTTP_CONNECT_TIMEOUT', 10),
        'accept_language' => env('ZATCA_ACCEPT_LANGUAGE', 'en'),
    ],

    // Path to an OpenSSL configuration file when the PHP build ships without a default one
    // (the Windows PHP build does). Leave empty on servers where OpenSSL finds its own config.
    'openssl_config' => env('ZATCA_OPENSSL_CONFIG'),

    // EGS (e-invoice generation solution) identity used in the CSR serial number:
    // "1-<solution provider>|2-<model or version>|3-<serial>" (Security Features v1.2, Table 1).
    'solution' => [
        'provider' => env('ZATCA_SOLUTION_PROVIDER', 'Seera ERP'),
        'version' => env('ZATCA_SOLUTION_VERSION', '1.0'),
    ],

    // Simplified documents must be reported within 24 hours of generation (Implementation
    // Resolution 2023-05-19, Clause Third B). Used only for the reporting-deadline indicator.
    'reporting_window_hours' => (int) env('ZATCA_REPORTING_WINDOW_HOURS', 24),

    // Signing-input convention for the ECDSA stamp (Detailed Technical Guidelines v2, 5.2 Step 2:
    // "Sign the generated invoice hash ... (not encoded)"): the raw SHA-256 digest bytes are signed
    // with ECDSA-SHA256. Kept here so the decision is visible; to be confirmed on the sandbox.
    'sign_raw_digest' => true,
];
