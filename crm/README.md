# DISTILLOGIC CRM — Plesk edition

This is the PHP 8.2 and MariaDB deployment of the internal DISTILLOGIC CRM.
It is intentionally independent from the archived Node.js/PostgreSQL implementation.

## Server requirements

- PHP 8.2+
- PDO MySQL, mbstring, fileinfo, curl and session extensions
- MariaDB 10.11+
- HTTPS before production sign-in

## Installation

1. Copy `config.example.php` to `config.php` on the server only.
2. Fill the MariaDB password and a long random setup token.
3. Visit `/crm/setup.php` and create the three initial account passwords.
4. Sign in at `/crm/`.

Never commit `config.php`.
