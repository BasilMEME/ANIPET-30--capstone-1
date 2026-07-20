<?php

define('USE_SMTP', true);

define(
    'SMTP_HOST',
    getenv('SMTP_HOST') ?: 'smtp.gmail.com'
);

define(
    'SMTP_PORT',
    (int) (getenv('SMTP_PORT') ?: 587)
);

define(
    'SMTP_USER',
    getenv('anipet.adoption@gmail.com') ?: ''
);

define(
    'SMTP_PASS',
    getenv('ubqy lqbh jxfx vnny') ?: ''
);

define(
    'SMTP_FROM_EMAIL',
    getenv('anipet.adoption@gmail.com') ?: SMTP_USER
);

define(
    'SMTP_FROM_NAME',
    getenv('Anipet') ?: 'Anipet'
);

define(
    'PHPMailer_AUTOLOAD',
    __DIR__ . '/vendor/autoload.php'
);