<?php
namespace Tests\Feature;

use App\Livewire\PropertyCatalog;
use App\Models\Property;
use App\Models\User;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;
    private function listing(array $overrides = []): Property
    {
        $user = User::firstOrCreate(['email' => 'seo@example.com'], ['name' => 'Propietario', 'password' => 'password123']);
        return $user->properties()->create(array_merge(['title' => 'Apartamento UNAH Kennedy', 'type' => 'apartamento', 'price' => '4500.50',
            'department' => 'Francisco Morazán', 'city' => 'Tegucigalpa', 'colonia' => 'Colonia Kennedy', 'address' => 'Frente a la UNAH, bloque A',
            'description' => 'Apartamento cerca de UNAH, con agua incluida.', 'whatsapp' => '98765432', 'is_available' => true,
            'images' => ['https://res.cloudinary.com/demo/image/upload/v1/alquilahn/properties/seo.jpg']], $overrides));
    }
    private function document(string $html): \DOMDocument
    {
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        return $document;
    }
    private function schema(string $html): array
    {
        $document = $this->document($html);
        return json_decode($document->getElementById('seo-schema')->textContent, true, flags: JSON_THROW_ON_ERROR);
    }
    public function test_home_has_one_heading_canonical_social_metadata_and_safe_schema(): void
    {
        $response = $this->get('/?q=&city=&utm_source=test');
        $response->assertOk()->assertSee('<title>'.config('seo.title').'</title>', false)
            ->assertSee('<link rel="canonical" href="'.Seo::url().'">', false)
            ->assertSee('content="index, follow"', false)->assertSee('content="es_HN"', false)
            ->assertSee('summary_large_image')->assertSee('loading="eager"', false)->assertSee('fetchpriority="high"', false);
        $this->assertSame(1, $this->document($response->getContent())->getElementsByTagName('h1')->length);
        $this->assertGreaterThanOrEqual(150, mb_strlen(config('seo.description')));
        $this->assertLessThanOrEqual(160, mb_strlen(config('seo.description')));
        $graph = $this->schema($response->getContent())['@graph'];
        $this->assertSame('WebSite', $graph[0]['@type']);
        $this->assertSame(Seo::url().'?q={search_term_string}', $graph[0]['potentialAction']['target']['urlTemplate']);
        $this->assertSame('Organization', $graph[1]['@type']);
        $this->assertSame('Honduras', $graph[1]['areaServed']['name']);
    }
    public function test_filtered_pages_have_specific_metadata_and_itemlist_with_hnl_offers(): void
    {
        $property = $this->listing();
        $response = $this->get('/?department=Francisco%20Moraz%C3%A1n&city=Tegucigalpa&type=apartamento&utm_source=test');
        $response->assertOk()->assertSee('<title>Alquiler de Apartamentos en Tegucigalpa, Francisco Morazán | AlquilaHN</title>', false);
        $schema = $this->schema($response->getContent());
        $item = $schema['@graph'][2]['itemListElement'][0]['item'];
        $this->assertSame('RealEstateListing', $item['@type']);
        $this->assertSame('4500.50', $item['offers']['price']);
        $this->assertSame('HNL', $item['offers']['priceCurrency']);
        $this->assertSame('https://schema.org/InStock', $item['offers']['availability']);
        $this->assertSame('HN', $item['offers']['itemOffered']['address']['addressCountry']);
        $this->assertSame(Seo::url(route('properties.show', $property, false)), $item['url']);
        $this->assertStringNotContainsString('utm_source', $schema['@graph'][2]['url']);
    }
    public function test_metadata_updates_with_applied_livewire_filters_and_pagination(): void
    {
        for ($i = 0; $i < 13; $i++) { $this->listing(['title' => 'Apartamento UNAH '.$i]); }
        Livewire::test(PropertyCatalog::class)->set('filters.city', 'Tegucigalpa')->set('filters.type', 'apartamento')->call('searchAll')
            ->assertDispatched('seo-updated', fn ($event, $params) => str_contains($params['seo']['title'], 'Apartamentos en Tegucigalpa'))
            ->call('gotoPage', 2)->assertDispatched('seo-updated', fn ($event, $params) => str_contains($params['seo']['canonical'], 'page=2'));
        $this->assertSame(['q' => 'UNAH', 'onlyAvailable' => '0', 'page' => 2], Seo::canonicalParameters(['q' => 'UNAH', 'onlyAvailable' => false, 'page' => 2, 'garbage' => 'test', 'city' => '']));
    }
    public function test_property_metadata_uses_real_image_and_unavailable_status(): void
    {
        $property = $this->listing(['is_available' => false, 'title' => 'Apartamento </script><script>alert(1)</script>']);
        $response = $this->get(route('properties.show', $property))->assertOk()->assertSee('property="og:image" content="'.$property->images[0].'"', false);
        $schema = $this->schema($response->getContent());
        $listing = $schema['@graph'][2];
        $this->assertSame('https://schema.org/OutOfStock', $listing['offers']['availability']);
        $this->assertSame($property->title, $listing['name']);
        $this->assertSame(1, $this->document($response->getContent())->getElementsByTagName('h1')->length);
        $this->assertStringNotContainsString('</script><script>alert(1)</script>', $response->getContent());
    }
    public function test_sitemaps_are_valid_xml_and_include_locations_and_all_public_listings(): void
    {
        $property = $this->listing(['is_available' => false]);
        $index = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $this->assertNotFalse(simplexml_load_string($index->getContent()));
        $index->assertSee(Seo::url('/sitemaps/locations.xml'), false)->assertSee(Seo::url('/sitemaps/properties-1.xml'), false);
        $locations = $this->get('/sitemaps/locations.xml')->assertOk();
        $this->assertNotFalse(simplexml_load_string($locations->getContent()));
        $locations->assertSee('department=Francisco%20Moraz%C3%A1n&amp;city=Tegucigalpa', false)->assertSee('department=Yoro', false);
        $properties = $this->get('/sitemaps/properties-1.xml')->assertOk();
        $this->assertNotFalse(simplexml_load_string($properties->getContent()));
        $properties->assertSee(Seo::url(route('properties.show', $property, false)), false)->assertSee('<lastmod>', false);
        $this->get('/sitemaps/properties-0.xml')->assertNotFound();
        $this->get('/sitemaps/properties-2.xml')->assertNotFound();
    }
    public function test_private_pages_are_noindex_and_robots_advertises_sitemap(): void
    {
        $this->get('/login')->assertOk()->assertSee('content="noindex, follow"', false);
        $this->get('/robots.txt')->assertOk()->assertSee('Allow: /')->assertSee('Disallow: /mis-anuncios')
            ->assertSee('Disallow: /login')->assertSee('Sitemap: '.Seo::url('/sitemap.xml'), false);
    }
}
