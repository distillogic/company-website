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
    'mail' => [
        'from_email' => 'info@distillogic.gr',
        'from_name' => 'DISTILLOGIC TECHNOLOGIES',
        'resend_api_key' => '',
    ],
];

