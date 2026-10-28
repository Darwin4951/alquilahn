@extends('layouts.app')
@section('content')
<div class="panel mx-auto max-w-md !p-8">
    <h1 class="mb-2 text-3xl font-bold">Publica tu inmueble</h1><p class="mb-6 text-slate-500">Crea tu cuenta para administrar tus anuncios.</p>
    <x-errors /><x-google-button />
    <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4">
        @csrf
        <div><label for="name">Nombre</label><input id="name" name="name" value="{{ old('name') }}" required autocomplete="name"></div>
        <div><label for="email">Correo electrónico</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email"></div>
        <div><label for="password">Contraseña (mínimo 8 caracteres)</label><input id="password" type="password" name="password" required minlength="8" autocomplete="new-password"></div>
        <div><label for="password_confirmation">Confirma tu contraseña</label><input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"></div>
        <button class="btn w-full">Crear cuenta</button>
    </form>
    <p class="mt-6 text-sm">¿Ya tienes cuenta? <a class="font-semibold text-teal-800" href="{{ route('login') }}">Inicia sesión</a></p>
</div>
@endsection
