@if(config('services.google.client_id'))
<a href="{{ route('google.redirect') }}" class="flex w-full items-center justify-center gap-3 rounded-xl border border-slate-300 bg-white px-5 py-3 font-semibold hover:bg-stone-50">
    <span aria-hidden="true" class="text-xl font-bold text-blue-600">G</span> Continuar con Google
</a>
@endif
