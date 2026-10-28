@props(['seo'])
<title>{{ $seo['title'] }}</title>
<meta name="description" content="{{ $seo['description'] }}">
<link rel="canonical" href="{{ $seo['canonical'] }}">
<meta name="robots" content="{{ $seo['robots'] }}">
@if(config('services.google.site_verification'))
<meta name="google-site-verification" content="{{ config('services.google.site_verification') }}">
@endif
<meta name="geo.region" content="HN">
<meta name="geo.placename" content="Honduras">
<meta property="og:locale" content="es_HN">
<meta property="og:site_name" content="AlquilaHN">
<meta property="og:type" content="website">
<meta property="og:title" content="{{ $seo['title'] }}">
<meta property="og:description" content="{{ $seo['description'] }}">
<meta property="og:image" content="{{ $seo['image'] }}">
<meta property="og:image:alt" content="AlquilaHN · Alquiler de inmuebles en Honduras">
<meta property="og:url" content="{{ $seo['canonical'] }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seo['title'] }}">
<meta name="twitter:description" content="{{ $seo['description'] }}">
<meta name="twitter:image" content="{{ $seo['image'] }}">
<script id="seo-schema" type="application/ld+json">{!! json_encode($seo['schema'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
