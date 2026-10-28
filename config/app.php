<?php
return [
    'name' => env('APP_NAME', 'AlquilaHN'), 'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false), 'url' => env('APP_URL', 'http://localhost:8000'),
    'timezone' => 'America/Tegucigalpa', 'locale' => 'es', 'fallback_locale' => 'en', 'faker_locale' => 'es_ES',
    'cipher' => 'AES-256-CBC', 'key' => env('APP_KEY'), 'previous_keys' => [],
    'maintenance' => ['driver' => 'file'],
];
