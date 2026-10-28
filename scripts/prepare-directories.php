<?php

// Composer runs this before Laravel boots, including on a fresh checkout.
$root = dirname(__DIR__);
foreach ([
    'bootstrap/cache',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/app/private',
    'storage/logs',
] as $directory) {
    $path = $root.'/'.$directory;
    if (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path)) {
        throw new RuntimeException('No se pudo crear el directorio: '.$path);
    }
}
