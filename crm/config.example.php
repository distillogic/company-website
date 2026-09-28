<?php
declare(strict_types=1);

return [
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'Distillogic-CRM',
        'user' => 'crm-app',
        'password' => 'REPLACE_WITH_THE_DATABASE_PASSWORD',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'base_path' => '/crm',
        'site_url' => 'https://www.distillogic.gr',
        'timezone' => 'Europe/Athens',
        'setup_token' => 'REPLACE_WITH_A_LONG_RANDOM_SETUP_TOKEN',
        'session_days' => 14,
    ],
    'company' => [
        'legal_name' => 'EXAMPLE COMPANY',
        'address' => 'Example Street 1, Patras, Greece',
        'vat_number' => 'EL000000000',
        'gemi_number' => '000000000000',
    ],
    'mail' => [
        'from_email' => 'account1@example.invalid',
        'from_name' => 'DISTILLOGIC TECHNOLOGIES',
        'resend_api_key' => '',
    ],
];
