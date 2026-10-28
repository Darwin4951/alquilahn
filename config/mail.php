<?php
return ['default' => env('MAIL_MAILER', 'log'), 'mailers' => [
    'log' => ['transport' => 'log'],
    'smtp' => ['transport' => 'smtp', 'host' => env('MAIL_HOST'), 'port' => env('MAIL_PORT', 587), 'encryption' => env('MAIL_ENCRYPTION', 'tls'), 'username' => env('MAIL_USERNAME'), 'password' => env('MAIL_PASSWORD')],
], 'from' => ['address' => env('MAIL_FROM_ADDRESS', 'hola@example.com'), 'name' => env('APP_NAME', 'AlquilaHN')]];
