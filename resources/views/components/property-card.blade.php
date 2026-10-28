@props(['property'])
<article class="group h-full overflow-hidden rounded-xl border border-stone-200/80 bg-white transition-shadow hover:shadow-lg hover:shadow-stone-900/5">
    <a href="{{ route('properties.show', $property) }}" class="relative block aspect-[4/3] overflow-hidden bg-stone-100">
        @if(count($property->images))<img src="{{ $property->images[0] }}" alt="{{ $property->title }} en alquiler en {{ $property->colonia }}, {{ $property->city }}, Honduras - AlquilaHN" loading="lazy" decoding="async" width="640" height="480" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105">@else<div class="flex h-full items-center justify-center text-sm text-stone-400">Sin fotografía</div>@endif
        <span class="absolute left-3 top-3 rounded-md px-2.5 py-1 text-[10px] font-semibold shadow-sm {{ $property->is_available ? 'bg-white/95 text-[#0F382F]' : 'bg-stone-800/90 text-white' }}">{{ $property->is_available ? 'Disponible' : 'No disponible' }}</span>
    </a>
    <div class="p-4">
        <p class="text-[10px] font-medium uppercase tracking-wider text-stone-500">{{ \App\Models\Property::TYPES[$property->type] }}</p>
        <h3 class="mt-2 text-sm font-semibold leading-snug tracking-tight text-[#18181b]"><a href="{{ route('properties.show', $property) }}">{{ $property->title }}</a></h3>
        <p class="mt-2 text-xs font-medium text-stone-700">{{ $property->colonia }}</p><p class="mt-1 text-[11px] leading-relaxed text-stone-500">{{ $property->city }}{{ $property->department ? ', '.$property->department : '' }}, Honduras</p>
        <div class="mt-4 border-t border-stone-100 pt-3"><span class="text-xl font-semibold tracking-tight text-[#0F382F]">L. {{ number_format((float) $property->price, 0) }}</span><span class="text-xs text-stone-500"> / mes</span></div>
        <a href="{{ $property->whatsappUrl() }}" target="_blank" rel="noopener noreferrer" class="mt-3 flex justify-center rounded-lg border border-stone-200 px-3 py-2.5 text-xs font-medium text-[#0F382F] transition-colors hover:border-[#0F382F]/30 hover:bg-stone-50">Contactar por WhatsApp ↗</a>
    </div>
</article>
