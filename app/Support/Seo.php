<?php
namespace App\Support;

use App\Models\Property;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class Seo
{
    public static function url(string $path = '/', array $parameters = []): string
    {
        $base = rtrim(config('app.url'), '/').'/'.ltrim($path, '/');
        return $base.($parameters ? '?'.http_build_query($parameters, '', '&', PHP_QUERY_RFC3986) : '');
    }

    public static function canonicalParameters(array $values): array
    {
        $clean = [];
        foreach (['department', 'city', 'type', 'search', 'q', 'minPrice', 'maxPrice', 'onlyAvailable', 'page', 'operation', 'brand', 'vehicleModel', 'minYear', 'maxYear', 'minArea', 'maxArea', 'areaUnit'] as $key) {
            $value = $values[$key] ?? '';
            if (! is_scalar($value)) { continue; }
            $value = is_bool($value) ? ($value ? '1' : '0') : trim((string) $value);
            if ($value === '') { continue; }
            if ($key === 'department' && ! in_array($value, Honduras::departments(), true)) { continue; }
            if ($key === 'city' && ! in_array($value, Honduras::cities($clean['department'] ?? ''), true)) { continue; }
            if ($key === 'type' && ! array_key_exists($value, Property::TYPES)) { continue; }
            if ($key === 'operation' && ! array_key_exists($value, Property::OPERATIONS)) { continue; }
            if ($key === 'areaUnit' && ! in_array($value, ['m2', 'v2'], true)) { continue; }
            if (in_array($key, ['minYear', 'maxYear', 'minArea', 'maxArea'], true) && (!is_numeric($value) || (float) $value < 0)) { continue; }
            if ($key === 'onlyAvailable') {
                if (in_array($value, ['0', 'false'], true)) { $clean[$key] = '0'; }
                continue;
            }
            if ($key === 'page') {
                if (ctype_digit($value) && (int) $value > 1) { $clean[$key] = (int) $value; }
                continue;
            }
            if (in_array($key, ['minPrice', 'maxPrice'], true)) {
                if (! is_numeric($value) || (float) $value < 0 || (float) $value > 99999999.99) { continue; }
                $value = rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
            }
            $clean[$key] = Str::limit($value, 150, '');
        }
        return $clean;
    }

    private static function base(string $title, string $description, string $canonical, string $robots = 'index, follow'): array
    {
        return ['title' => $title, 'description' => $description, 'canonical' => $canonical,
            'image' => config('seo.image'), 'robots' => $robots, 'schema' => self::graph()];
    }

    public static function defaults(): array
    {
        return self::base(config('seo.title'), config('seo.description'), self::url(request()->getPathInfo()), 'noindex, follow');
    }

    public static function catalog(array $values, LengthAwarePaginator $properties, bool $valid = true): array
    {
        $parameters = self::canonicalParameters(array_merge($values, ['page' => $properties->currentPage()]));
        $city = $parameters['city'] ?? '';
        $department = $parameters['department'] ?? ($city !== '' ? Honduras::departmentFor($city) : null);
        $types = ['casa' => 'Casas', 'apartamento' => 'Apartamentos', 'cuarto' => 'Cuartos', 'local' => 'Locales comerciales', 'terreno' => 'Terrenos', 'vehiculo' => 'Vehículos'];
        $type = $types[$parameters['type'] ?? ''] ?? null;
        $location = $city !== '' ? $city.($department ? ', '.$department : '') : ($department ?: 'Honduras');
        $title = config('seo.title');
        if ($type || $city !== '' || $department) { $title = 'Alquiler de '.($type ?: 'inmuebles').' en '.$location.' | AlquilaHN'; }
        elseif (! empty($parameters['q']) || ! empty($parameters['search'])) { $title = 'Alquileres: '.Str::limit($parameters['q'] ?? $parameters['search'], 60).' | AlquilaHN'; }
        if ($properties->currentPage() > 1) { $title .= ' · Página '.$properties->currentPage(); }
        $description = config('seo.description');
        if ($type || $city !== '' || $department) {
            $description = self::description('Encuentra '.Str::lower($type ?: 'casas, apartamentos y cuartos').' en alquiler en '.$location.'. Compara precios en lempiras y contacta directamente al propietario, sin intermediarios en AlquilaHN.');
        }
        $seo = self::base($title, $description, self::url('/', $parameters), $valid ? 'index, follow' : 'noindex, follow');
        $seo['heading'] = ($type || $city !== '' || $department) ? 'Alquiler de '.Str::lower($type ?: 'inmuebles').' en '.$location : 'Alquiler de casas, apartamentos y cuartos en Honduras';
        if (($parameters['operation'] ?? '') === 'venta' || in_array($parameters['type'] ?? '', ['terreno', 'vehiculo'], true)) {
            $seo['heading'] = ($type ?: 'Anuncios').' en venta en '.$location;
            $seo['title'] = $seo['heading'].' | AlquilaHN'.($properties->currentPage() > 1 ? ' · Página '.$properties->currentPage() : '');
            $seo['description'] = self::description('Encuentra '.Str::lower($type ?: 'anuncios').' en venta en '.$location.'. Precios en lempiras y contacto directo por WhatsApp.');
        }
        if ($properties->count()) {
            $items = [];
            foreach ($properties as $index => $property) {
                $items[] = ['@type' => 'ListItem', 'position' => $properties->firstItem() + $index,
                    'url' => self::url(route('properties.show', $property, false)), 'item' => self::listing($property)];
            }
            $seo['schema']['@graph'][] = ['@type' => 'ItemList', '@id' => $seo['canonical'].'#listings',
                'url' => $seo['canonical'], 'name' => $seo['heading'], 'numberOfItems' => count($items), 'itemListElement' => $items];
        }
        return $seo;
    }

    public static function property(Property $property): array
    {
        $seo = self::base($property->title.' en '.$property->city.' | AlquilaHN', self::description(
            Property::TYPES[$property->type].' en '.($property->isSale() ? 'venta' : 'alquiler').' en '.$property->colonia.', '.$property->city.'. L. '.number_format((float) $property->price, 2).($property->isSale() ? ' precio total. ' : ' al mes. ').$property->description
        ), self::url(route('properties.show', $property, false)));
        $seo['image'] = $property->images[0] ?? config('seo.image');
        $seo['schema']['@graph'][] = self::listing($property);
        return $seo;
    }

    private static function description(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));
        return Str::limit($text, 157, '…');
    }

    public static function graph(): array
    {
        $home = self::url();
        return ['@context' => 'https://schema.org', '@graph' => [
            ['@type' => 'WebSite', '@id' => $home.'#website', 'name' => 'AlquilaHN', 'url' => $home, 'inLanguage' => 'es-HN',
                'publisher' => ['@id' => $home.'#organization'], 'potentialAction' => ['@type' => 'SearchAction',
                    'target' => ['@type' => 'EntryPoint', 'urlTemplate' => $home.'?q={search_term_string}'], 'query-input' => 'required name=search_term_string']],
            ['@type' => 'Organization', '@id' => $home.'#organization', 'name' => 'AlquilaHN', 'url' => $home,
                'description' => 'Portal de alquiler de inmuebles en Honduras con contacto directo con el propietario.',
                'areaServed' => ['@type' => 'Country', 'name' => 'Honduras', 'identifier' => 'HN']],
        ]];
    }

    public static function listing(Property $property): array
    {
        $url = self::url(route('properties.show', $property, false));
        $listing = ['@type' => $property->type === 'vehiculo' ? 'Product' : 'RealEstateListing', '@id' => $url.'#listing', 'url' => $url, 'name' => $property->title,
            'description' => strip_tags($property->description ?? ''), 'image' => $property->images,
            'datePosted' => $property->created_at?->toAtomString(), 'dateModified' => $property->updated_at?->toAtomString(),
            'inLanguage' => 'es-HN', 'isPartOf' => ['@id' => self::url().'#website'],
            'offers' => ['@type' => 'Offer', 'url' => $url, 'price' => $property->price, 'priceCurrency' => 'HNL',
                'businessFunction' => 'http://purl.org/goodrelations/v1#'.($property->isSale() ? 'Sell' : 'LeaseOut'),
                'availability' => 'https://schema.org/'.($property->is_available ? 'InStock' : 'OutOfStock'),
                'priceSpecification' => ['@type' => 'UnitPriceSpecification', 'price' => $property->price, 'priceCurrency' => 'HNL', 'unitText' => 'mes', 'billingDuration' => 'P1M'],
                'itemOffered' => ['@type' => [match ($property->type) { 'casa' => 'House', 'apartamento' => 'Apartment', 'terreno' => 'Landform', 'local' => 'Place', 'vehiculo' => 'Vehicle', default => 'Room' }, 'Product'],
                    'name' => $property->title, 'address' => ['@type' => 'PostalAddress',
                        'streetAddress' => $property->colonia.', '.$property->address, 'addressLocality' => $property->city,
                        'addressRegion' => $property->department ?? Honduras::departmentFor($property->city), 'addressCountry' => 'HN']]],
        ];
        if ($property->isSale()) { unset($listing['offers']['priceSpecification']); }
        if ($property->type === 'vehiculo') {
            unset($listing['offers']['itemOffered']['address']);
            $listing['offers']['itemOffered'] += ['brand' => $property->brand, 'model' => $property->vehicle_model, 'vehicleModelDate' => $property->year, 'mileageFromOdometer' => ['@type' => 'QuantitativeValue', 'value' => $property->mileage, 'unitCode' => 'KMT']];
        }
        return $listing;
    }
}
