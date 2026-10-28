<?php
namespace App\Services;
use Cloudinary\Cloudinary;
use App\Exceptions\ImageStorageNotConfigured;
use Illuminate\Support\Facades\Storage;
class PropertyImages
{
    public function upload($file): string
    {
        $url = config('filesystems.disks.cloudinary.url');
        // Local development can publish without a Cloudinary account.
        if ((! is_string($url) || trim($url) === '') && app()->environment('local')) {
            $path = Storage::disk('public')->putFile('properties', $file);
            return Storage::disk('public')->url($path);
        }
        $credentials = is_string($url) ? parse_url($url) : false;
        if (! $credentials || ($credentials['scheme'] ?? '') !== 'cloudinary'
            || empty($credentials['host']) || empty($credentials['user']) || empty($credentials['pass'])
            || in_array($credentials['user'], ['API_KEY', 'your_api_key'], true)
            || in_array($credentials['pass'], ['API_SECRET', 'your_api_secret'], true)
            || in_array($credentials['host'], ['CLOUD_NAME', 'your_cloud_name'], true)) {
            throw new ImageStorageNotConfigured('El almacenamiento de imágenes no está configurado.');
        }
        $result = app(Cloudinary::class)->uploadApi()->upload($file->getRealPath(), [
            'folder' => 'alquilahn/properties', 'resource_type' => 'image',
            'allowed_formats' => ['jpg', 'jpeg', 'png', 'webp'],
            'transformation' => [['width' => 1800, 'height' => 1800, 'crop' => 'limit']],
        ]);
        $url = $result['secure_url'] ?? null;
        if (! is_string($url) || ! str_starts_with($url, 'https://res.cloudinary.com/')) { throw new \RuntimeException('Cloudinary no devolvió una URL segura.'); }
        return $url;
    }
    public function delete(string $url): void
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && preg_match('~^/storage/(properties/[A-Za-z0-9._-]+)$~', $path, $matches)) {
            Storage::disk('public')->delete($matches[1]);
            return;
        }
        // Derive the ID only from this application's untransformed upload URLs.
        if (parse_url($url, PHP_URL_HOST) !== 'res.cloudinary.com' || ! is_string($path) || ! preg_match('~/image/upload/(?:v\d+/)?(alquilahn/properties/.+)\.[a-zA-Z0-9]+$~', $path, $matches)) { return; }
        app(Cloudinary::class)->uploadApi()->destroy($matches[1], ['resource_type' => 'image', 'invalidate' => true]);
    }
    public function cleanup(array $urls): void
    {
        foreach ($urls as $url) {
            try { $this->delete($url); } catch (\Throwable $exception) { report($exception); }
        }
    }
}
