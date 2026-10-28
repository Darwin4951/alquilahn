<?php
return ['default' => 'local', 'disks' => [
    'local' => ['driver' => 'local', 'root' => storage_path('app/private'), 'throw' => true],
    'public' => ['driver' => 'local', 'root' => storage_path('app/public'), 'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage', 'visibility' => 'public', 'throw' => true],
    'cloudinary' => ['driver' => 'cloudinary', 'url' => env('CLOUDINARY_URL'), 'secure' => true],
]];
