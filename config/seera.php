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
];
