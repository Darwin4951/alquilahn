<?php
return [
    'default' => env('DB_CONNECTION', 'sqlite'),
    'connections' => [
        'sqlite' => ['driver' => 'sqlite', 'url' => env('DB_URL'), 'database' => env('DB_DATABASE', database_path('database.sqlite')), 'prefix' => '', 'foreign_key_constraints' => true, 'busy_timeout' => 5000],
        'mysql' => [
            'driver' => 'mysql', 'url' => env('DB_URL'), 'host' => env('DB_HOST', '127.0.0.1'), 'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'defaultdb'), 'username' => env('DB_USERNAME'), 'password' => env('DB_PASSWORD'),
            'unix_socket' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'prefix_indexes' => true, 'strict' => true,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                \Pdo\Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                \Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT => true,
            ], fn ($value) => $value !== null && $value !== '') : [],
        ],
    ],
    'migrations' => ['table' => 'migrations', 'update_date_on_publish' => true],
];
