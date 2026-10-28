<div>
    <section aria-labelledby="hero-title" class="relative isolate -mx-4 min-h-[380px] overflow-hidden bg-stone-800 sm:-mx-6 lg:-mx-8">
        <img src="https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?auto=format&amp;fit=crop&amp;w=2000&amp;h=1125&amp;q=85" alt="Arquitectura residencial de referencia para explorar casas en alquiler en Honduras - AlquilaHN" fetchpriority="high" loading="eager" decoding="async" width="2000" height="1125" class="absolute inset-0 -z-20 h-full w-full object-cover object-[center_55%]">
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-stone-950/85 via-stone-950/60 to-transparent"></div>
        <div class="px-6 pb-36 pt-12 sm:px-10 sm:pt-16 lg:px-14 lg:pb-32">
            <p class="mb-4 text-[11px] font-semibold uppercase tracking-[0.22em] text-amber-300">Un lugar para tu siguiente capítulo</p>
            <h1 id="hero-title" class="max-w-2xl text-4xl font-semibold leading-[1.12] tracking-[-0.04em] text-white sm:text-5xl lg:text-[56px]">{{ $seo['heading'] }}</h1>
            <p class="mt-5 max-w-lg text-sm leading-relaxed text-white/80 sm:text-base">El espacio que buscas, en la zona que te gusta.<br class="hidden sm:block"> Alquila directamente con el propietario.</p>
        </div>
    </section>

    <form wire:submit="searchAll" role="search" aria-label="Buscar inmuebles en Honduras" class="relative z-10 mx-0 -mt-20 rounded-2xl border border-stone-200/80 bg-white p-3 shadow-xl shadow-stone-900/10 sm:mx-3 lg:mx-5">
        <div class="grid divide-y divide-stone-200 lg:grid-cols-[minmax(0,2.4fr)_minmax(0,1fr)_minmax(0,1.25fr)_minmax(0,1fr)] lg:divide-x lg:divide-y-0">
            <fieldset class="min-w-0 px-3 pb-4 pt-2 lg:py-2 lg:pr-5">
                <legend class="sr-only">Ubicación y palabras clave</legend>
                <label for="general-search" class="search-label">¿Dónde quieres vivir?</label>
                <div class="mb-2 flex items-center gap-2"><x-catalog-icon name="search" class="!h-4 !w-4 text-stone-400"/><input id="general-search" type="search" wire:model="keywords" maxlength="150" placeholder="Apartamento UNAH, cuarto Kennedy…" class="min-w-0 flex-1 rounded-lg border-0 p-0 text-sm font-medium placeholder-stone-400 focus:ring-0" aria-label="Palabras clave"></div>
                <div class="grid grid-cols-2 gap-2">
                    <div><label for="filter-department" class="sr-only">Departamento</label><select id="filter-department" wire:model.live="filters.department" class="search-control"><option value="">Departamento</option>@foreach($departments as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach</select></div>
                    <div><label for="filter-city" class="sr-only">Ciudad / municipio</label><select id="filter-city" wire:model="filters.city" wire:key="catalog-city-{{ $draftDepartment }}" class="search-control"><option value="">Ciudad / municipio</option>@foreach($cityGroups as $group => $options)<optgroup label="{{ $group }}">@foreach($options as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach</optgroup>@endforeach</select></div>
                    <div class="col-span-2"><label for="filter-search" class="sr-only">Colonia, barrio o dirección</label><input id="filter-search" wire:model="filters.search" maxlength="150" placeholder="Colonia, barrio o dirección" class="search-control"></div>
                </div>
            </fieldset>
            <div class="min-w-0 px-3 py-4 lg:px-4 lg:py-2"><label for="filter-type" class="search-label">Tipo de inmueble</label><select id="filter-type" wire:model="filters.type" class="search-control !bg-white !px-0 !text-sm"><option value="">Cualquier tipo</option>@foreach($types as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select><p class="mt-2 text-xs leading-relaxed text-stone-400">Un espacio a tu medida.</p></div>
            <fieldset class="min-w-0 px-3 py-4 lg:px-4 lg:py-2"><legend class="sr-only">Presupuesto mensual en lempiras</legend><p class="search-label">Presupuesto mensual</p><div class="mb-2 text-xs text-stone-400">Lempiras / mes</div><div class="grid grid-cols-2 gap-2"><input type="number" min="0" step="0.01" wire:model="filters.minPrice" placeholder="Desde" aria-label="Precio mínimo" class="search-control"><input type="number" min="0" step="0.01" wire:model="filters.maxPrice" placeholder="Hasta" aria-label="Precio máximo" class="search-control"></div></fieldset>
            <div class="flex flex-col justify-center gap-3 px-3 py-4 lg:py-2 lg:pl-5">
                <button type="submit" aria-label="Buscar inmuebles" class="flex items-center justify-center gap-2 whitespace-nowrap rounded-xl bg-[#0F382F] px-4 py-3.5 text-sm font-semibold text-white transition-colors hover:bg-[#164a3e] disabled:opacity-60 xl:px-6" wire:loading.attr="disabled" wire:target="searchAll"><x-catalog-icon name="search" class="!h-4 !w-4"/><span wire:loading.remove wire:target="searchAll">Buscar</span><span wire:loading wire:target="searchAll">Buscando…</span></button>
                <label class="flex cursor-pointer items-center justify-center gap-2 text-xs font-medium text-stone-500"><input type="checkbox" wire:model="filters.onlyAvailable" class="h-3.5 w-3.5 rounded border-stone-300 text-[#0F382F] focus:ring-[#0F382F]/20"> Solo disponibles</label>
            </div>
        </div>
        @if($errors->any())<div class="px-3 pt-3"><x-errors /></div>@endif
    </form>

    <nav aria-label="Categorías rápidas" class="mb-9 mt-6 flex items-center gap-6 overflow-x-auto border-b border-stone-200 lg:justify-between lg:gap-4">
        <a href="{{ route('catalog', ['type' => 'casa']) }}" class="category-link {{ $type === 'casa' ? '!border-[#0F382F] !text-[#0F382F]' : '' }}"><x-catalog-icon name="home"/>Casas</a>
        <a href="{{ route('catalog', ['type' => 'apartamento']) }}" class="category-link {{ $type === 'apartamento' ? '!border-[#0F382F] !text-[#0F382F]' : '' }}"><x-catalog-icon name="building"/>Apartamentos</a>
        <a href="{{ route('catalog', ['type' => 'cuarto', 'q' => 'universidad']) }}" class="category-link"><x-catalog-icon name="bed"/>Cuartos universitarios</a>
        <a href="{{ route('catalog', ['q' => 'local comercial']) }}" class="category-link"><x-catalog-icon name="shop"/>Locales comerciales</a>
        <a href="{{ route('catalog', ['q' => 'amueblado']) }}" class="category-link"><x-catalog-icon name="sofa"/>Amueblados</a>
        <a href="{{ route('catalog', ['q' => 'UNAH']) }}" class="category-link"><x-catalog-icon name="university"/>Cerca de UNAH</a>
        <a href="{{ route('catalog', ['city' => 'Tegucigalpa']) }}" class="category-link"><x-catalog-icon name="pin"/>Tegucigalpa</a>
        <a href="{{ route('catalog', ['city' => 'San Pedro Sula']) }}" class="category-link"><x-catalog-icon name="pin"/>San Pedro Sula</a>
    </nav>

    <section aria-labelledby="results-title" class="w-full" wire:loading.class="opacity-60">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3"><div><h2 id="results-title" class="text-2xl font-semibold tracking-tight text-[#18181b]">{{ $onlyAvailable ? 'Espacios disponibles para ti' : 'Espacios para ti' }}</h2><p class="mt-1 text-sm text-stone-500">{{ $q !== '' ? 'Resultados para “'.$q.'”' : 'Encuentra el lugar donde empieza lo que sigue.' }}</p></div><div class="flex items-center gap-4"><span aria-live="polite" class="text-xs font-medium text-stone-500">{{ $properties->total() }} anuncios</span>@if($q !== '' || $department !== '' || $city !== '' || $search !== '' || $type !== '' || $minPrice !== '' || $maxPrice !== '' || !$onlyAvailable)<button type="button" wire:click="clearFilters" class="text-xs font-medium text-[#0F382F] underline decoration-stone-300 underline-offset-4">Restablecer búsqueda</button>@endif</div></div>
        @if($properties->count())
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">@foreach($properties as $property)<div wire:key="property-{{ $property->id }}" class="min-w-0"><x-property-card :property="$property" /></div>@endforeach</div>
            <div class="mt-8">{{ $properties->links() }}</div>
        @else
            <div class="flex flex-col items-start justify-between gap-4 rounded-xl border border-stone-200 bg-white px-5 py-5 sm:flex-row sm:items-center"><div class="flex items-start gap-3"><div class="mt-0.5 text-stone-400"><x-catalog-icon name="search"/></div><div><h3 class="text-sm font-semibold text-stone-800">No encontramos resultados exactos con estos filtros</h3><p class="mt-1 text-xs leading-relaxed text-stone-500">Prueba otra ciudad, cambia las palabras o amplía tu presupuesto.</p></div></div><button type="button" wire:click="clearFilters" class="shrink-0 rounded-lg border border-stone-300 bg-white px-4 py-2.5 text-xs font-semibold text-[#0F382F] transition-colors hover:bg-stone-50">Restablecer búsqueda</button></div>
            <section aria-labelledby="popular-title" class="mt-10">
                <div class="mb-5 flex flex-wrap items-end justify-between gap-3"><div><p class="mb-2 text-[10px] font-semibold uppercase tracking-[0.18em] text-[#D97706]">Tu próxima dirección</p><h2 id="popular-title" class="text-xl font-semibold tracking-tight text-[#18181b]">Explora alquileres por zona en Honduras</h2></div><p class="text-[10px] text-stone-400">Fotografías de referencia · Explora anuncios por zona</p></div>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
                    <x-popular-zone city="Tegucigalpa" zone="Zona Universitaria" :parameters="['city' => 'Tegucigalpa', 'q' => 'UNAH']" photo="photo-1522708323590-d24dbb6b0267"/>
                    <x-popular-zone city="San Pedro Sula" zone="Río de Piedras" :parameters="['city' => 'San Pedro Sula', 'search' => 'Río de Piedras']" photo="photo-1600210492486-724fe5c67fb0"/>
                    <x-popular-zone city="La Ceiba" zone="Barrio El Centro" :parameters="['city' => 'La Ceiba', 'search' => 'El Centro']" photo="photo-1600607687939-ce8a6c25118c"/>
                    <x-popular-zone city="Comayagua" zone="Centro de la ciudad" :parameters="['city' => 'Comayagua', 'search' => 'Centro']" photo="photo-1600566753086-00f18fb6b3ea"/>
                </div>
            </section>
        @endif
    </section>
</div>
