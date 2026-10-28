@php
    $groups = [
        'Alquileres por ciudad' => [
            ['Alquileres en Tegucigalpa', ['city' => 'Tegucigalpa']],
            ['Alquileres en San Pedro Sula', ['city' => 'San Pedro Sula']],
            ['Alquileres en La Ceiba', ['city' => 'La Ceiba']],
            ['Alquileres en Choluteca', ['city' => 'Choluteca']],
            ['Alquileres en Comayagua', ['city' => 'Comayagua']],
        ],
        'Zonas populares y universitarias' => [
            ['Cuartos cerca de la UNAH Tegucigalpa', ['city' => 'Tegucigalpa', 'type' => 'cuarto', 'q' => 'UNAH']],
            ['Apartamentos en Colonia Kennedy', ['city' => 'Tegucigalpa', 'type' => 'apartamento', 'search' => 'Kennedy']],
            ['Alquileres en Residencial Las Uvas', ['city' => 'Tegucigalpa', 'search' => 'Las Uvas']],
            ['Apartamentos en Río de Piedras SPS', ['city' => 'San Pedro Sula', 'type' => 'apartamento', 'search' => 'Río de Piedras']],
        ],
        'Encuentra tu tipo de inmueble' => [
            ['Casas en alquiler en Honduras', ['type' => 'casa']],
            ['Apartamentos amueblados', ['type' => 'apartamento', 'q' => 'amueblado']],
            ['Cuartos para estudiantes', ['type' => 'cuarto', 'q' => 'estudiantes']],
            ['Locales comerciales', ['q' => 'local comercial']],
        ],
    ];
@endphp
<nav aria-label="Explora alquileres en Honduras" class="grid gap-8 pb-9 sm:grid-cols-2 lg:grid-cols-3">
    @foreach($groups as $heading => $links)
        <section><h2 class="mb-4 text-xs font-semibold tracking-tight text-stone-800">{{ $heading }}</h2><ul class="space-y-3">@foreach($links as [$label, $parameters])<li><a href="{{ route('catalog', $parameters) }}" class="text-xs leading-relaxed text-stone-500 transition-colors hover:text-[#0F382F] hover:underline">{{ $label }}</a></li>@endforeach</ul></section>
    @endforeach
</nav>
