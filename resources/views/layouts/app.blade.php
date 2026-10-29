<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <x-seo-head :seo="$seo ?? \App\Support\Seo::defaults()" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen">
    <a href="#contenido" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[60] focus:rounded-lg focus:bg-white focus:p-3">Ir al contenido</a>
    <header class="sticky top-0 z-50 border-b border-stone-200/80 bg-white/90 backdrop-blur-md">
        <nav class="mx-auto flex min-h-[76px] max-w-7xl flex-wrap items-center justify-between gap-x-6 gap-y-3 px-4 py-4 sm:px-6 lg:px-8" aria-label="Navegación principal">
            <a href="{{ route('catalog') }}" class="flex items-center text-xl font-bold tracking-tight text-[#0F382F]">Alquila<span class="text-[#D97706]">HN</span><span class="mx-3 hidden h-4 w-px bg-stone-300 lg:block" aria-hidden="true"></span><span class="hidden text-xs font-normal tracking-normal text-stone-500 lg:inline">Tu próximo hogar, aquí.</span></a>
            <div class="flex flex-wrap items-center gap-4 sm:gap-6">
                <a href="{{ route('catalog') }}" class="text-sm font-medium text-stone-600 transition-colors hover:text-stone-900">Explorar</a>
                @foreach(['Alquileres' => ['operation' => 'alquiler'], 'Casas en venta' => ['operation' => 'venta', 'type' => 'casa'], 'Terrenos en venta' => ['operation' => 'venta', 'type' => 'terreno'], 'Vehículos en venta' => ['operation' => 'venta', 'type' => 'vehiculo']] as $label => $parameters)
                <a href="{{ route('catalog', $parameters) }}" class="text-sm font-medium text-stone-600 transition-colors hover:text-stone-900">{{ $label }}</a>
                @endforeach
                @auth
                    <a href="{{ route('properties.manage') }}" class="rounded-lg bg-[#0F382F] px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-[#164a3e]">Mis anuncios</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="text-sm font-medium text-stone-600 hover:text-stone-900">Salir</button></form>
                @else
                    <a href="{{ route('login') }}" class="text-sm font-medium text-stone-600 transition-colors hover:text-stone-900">Ingresar</a><a class="rounded-lg bg-[#0F382F] px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-[#164a3e]" href="{{ route('register') }}">Publicar anuncio</a>
                @endauth
            </div>
        </nav>
    </header>
    <main id="contenido" class="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
        @if(session('status'))<div role="status" class="mb-6 rounded-xl bg-teal-50 p-4 text-teal-900">{{ session('status') }}</div>@endif
        {{ $slot ?? '' }}
        @yield('content')
    </main>
    <footer class="mx-auto mt-14 max-w-7xl border-t border-stone-200 px-4 pt-9 text-xs text-stone-500 sm:px-6 lg:px-8">
        <x-seo-links />
        <div class="grid gap-5 border-t border-stone-200 py-6 sm:grid-cols-2 sm:items-center lg:grid-cols-3">
            <div><p class="font-semibold text-[#0F382F]">AlquilaHN</p><p class="mt-1 text-stone-400">Alquileres en Honduras</p></div>
            <div>
                <p class="font-semibold text-stone-700">Contacto</p>
                <div class="mt-1 flex flex-col items-start gap-1.5">
                    <a href="mailto:garmendiaddarwin@gmail.com" class="transition-colors hover:text-[#0F382F]">garmendiaddarwin@gmail.com</a>
                    <a href="https://wa.me/50497494465" target="_blank" rel="noopener noreferrer" aria-label="Contactar por WhatsApp al +504 9749-4465" class="transition-colors hover:text-[#0F382F]">WhatsApp: +504 9749-4465</a>
                </div>
            </div>
            <p class="text-stone-500">Contacta directamente con el propietario.</p>
        </div>
    </footer>
    @livewireScripts
</body>
</html>
