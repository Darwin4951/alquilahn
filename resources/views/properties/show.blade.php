@extends('layouts.app')
@section('content')
<a href="{{ route('catalog') }}" class="text-sm font-semibold text-teal-800">← Volver al catálogo</a>
<article class="mt-6">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-5"><div><p class="text-sm font-bold uppercase tracking-wider text-teal-700">{{ \App\Models\Property::TYPES[$property->type] }}</p><h1 class="mt-2 text-3xl font-bold">{{ $property->title }}</h1><p class="mt-3 text-lg"><strong>{{ $property->colonia }}</strong> · {{ $property->city }}{{ $property->department ? ', '.$property->department : '' }}, Honduras</p></div><span class="rounded-full px-4 py-2 text-sm font-bold {{ $property->is_available ? 'bg-emerald-100 text-emerald-900' : 'bg-red-100 text-red-900' }}">{{ $property->is_available ? 'Disponible' : 'No disponible / Alquilado' }}</span></div>
    <div class="grid gap-4 sm:grid-cols-2">@foreach($property->images as $image)<a href="{{ $image }}" target="_blank" rel="noopener noreferrer"><img src="{{ $image }}" alt="Foto {{ $loop->iteration }} de {{ $property->title }} en alquiler en {{ $property->city }}, Honduras - AlquilaHN" decoding="async" class="h-72 w-full rounded-2xl object-cover" loading="{{ $loop->first ? 'eager' : 'lazy' }}" fetchpriority="{{ $loop->first ? 'high' : 'auto' }}"></a>@endforeach</div>
    <div class="mt-8 grid items-start gap-7 lg:grid-cols-[1fr_340px]">
        <div class="panel space-y-6"><section><h2 class="mb-3 text-xl font-bold">Acerca de este inmueble</h2><p class="whitespace-pre-line leading-relaxed text-slate-600">{{ $property->description }}</p></section><section><h2 class="mb-3 text-xl font-bold">Dirección y referencias</h2><p class="whitespace-pre-line text-slate-600">{{ $property->address }}</p></section></div>
        <aside class="panel"><p class="text-3xl font-black text-teal-900">L. {{ number_format((float) $property->price, 2) }}<span class="text-sm font-normal text-slate-500"> / mes</span></p><p class="my-4 text-sm text-slate-600">Consulta los detalles directamente con el propietario.</p><a class="btn w-full" href="{{ $property->whatsappUrl() }}" target="_blank" rel="noopener noreferrer">Contactar por WhatsApp ↗</a></aside>
    </div>
</article>
@endsection
