<?php
namespace App\Http\Controllers;

use App\Models\Property;
use App\Support\Honduras;
use App\Support\Seo;

class SitemapController extends Controller
{
    private const PAGE_SIZE = 1000;
    public function index()
    {
        $entries = [['loc' => Seo::url('/sitemaps/locations.xml')]];
        $pages = (int) ceil(Property::count() / self::PAGE_SIZE);
        for ($page = 1; $page <= $pages; $page++) { $entries[] = ['loc' => Seo::url('/sitemaps/properties-'.$page.'.xml')]; }
        return $this->xml('sitemapindex', 'sitemap', $entries);
    }
    public function locations()
    {
        $entries = [['loc' => Seo::url()]];
        foreach (array_keys(Property::TYPES) as $type) { $entries[] = ['loc' => Seo::url('/', ['type' => $type])]; }
        foreach (Honduras::groups() as $department => $cities) {
            $entries[] = ['loc' => Seo::url('/', ['department' => $department])];
            foreach ($cities as $city) { $entries[] = ['loc' => Seo::url('/', ['department' => $department, 'city' => $city])]; }
        }
        return $this->xml('urlset', 'url', $entries);
    }
    public function properties(string $page)
    {
        abort_unless(ctype_digit($page) && (int) $page > 0 && (int) $page <= 2147483, 404);
        $properties = Property::orderBy('id')->forPage((int) $page, self::PAGE_SIZE)->get(['id', 'updated_at']);
        abort_if($properties->isEmpty(), 404);
        $entries = $properties->map(fn ($property) => ['loc' => Seo::url(route('properties.show', $property, false)), 'lastmod' => $property->updated_at->toAtomString()])->all();
        return $this->xml('urlset', 'url', $entries);
    }
    public function robots()
    {
        $text = "User-agent: *\nAllow: /\n";
        foreach (['/admin', '/login', '/register', '/logout', '/forgot-password', '/reset-password', '/auth/', '/mis-anuncios', '/livewire/', '/up'] as $path) { $text .= 'Disallow: '.$path."\n"; }
        return response($text."\nSitemap: ".Seo::url('/sitemap.xml')."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
    private function xml(string $root, string $element, array $entries)
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<'.$root.' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($entries as $entry) {
            $xml .= '<'.$element.'>';
            foreach ($entry as $key => $value) { $xml .= '<'.$key.'>'.htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</'.$key.'>'; }
            $xml .= '</'.$element.'>';
        }
        return response($xml.'</'.$root.'>', 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=300']);
    }
}
