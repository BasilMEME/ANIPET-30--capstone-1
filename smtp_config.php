<?php

declare(strict_types=1);

define(
    'RESEND_API_KEY',
    getenv('RESEND_API_KEY') ?: ''
);

define(
    'RESEND_FROM_EMAIL',
    getenv('RESEND_FROM_EMAIL')
        ?: 'onboarding@resend.dev'
);

define(
    'RESEND_FROM_NAME',
    getenv('RESEND_FROM_NAME')
        ?: 'Anipet'
);

define(
    'PHPMailer_AUTOLOAD',
    __DIR__ . '/vendor/autoload.php'
);