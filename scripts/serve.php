<?php
// Development server with upload limits matching the production container.
// PHP's -d flags cannot be changed with ini_set for upload/post limits.
$root = dirname(__DIR__);
chdir($root.'/public');
$router = $root.'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php';
if (! is_file($router)) {
    fwrite(STDERR, "Ejecuta composer install antes de iniciar el servidor.\n");
    exit(1);
}
$command = escapeshellarg(PHP_BINARY)
    .' -d upload_max_filesize=6M -d post_max_size=48M -d max_file_uploads=8'
    .' -S 127.0.0.1:8000 '.escapeshellarg($router);
echo "AlquilaHN: http://127.0.0.1:8000 (hasta 8 fotos, 5 MB por foto).\n";
passthru($command, $status);
exit($status);
