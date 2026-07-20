<?php

declare(strict_types=1);

define(
    'GOOGLE_CLIENT_ID',
    getenv('GOOGLE_CLIENT_ID') ?: ''
);

define(
    'GOOGLE_CLIENT_SECRET',
    getenv('GOOGLE_CLIENT_SECRET') ?: ''
);

define(
    'GOOGLE_REFRESH_TOKEN',
    getenv('GOOGLE_REFRESH_TOKEN') ?: ''
);

define(
    'GMAIL_FROM_EMAIL',
    getenv('GMAIL_FROM_EMAIL')
        ?: 'anipet.adoption@gmail.com'
);

define(
    'GMAIL_FROM_NAME',
    getenv('GMAIL_FROM_NAME')
        ?: 'AniPet'
);