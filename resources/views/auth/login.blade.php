@extends('layouts.app')
@section('content')
<div class="panel mx-auto max-w-md !p-8">
    <p class="text-sm font-bold uppercase tracking-widest text-teal-700">Bienvenido a casa</p>
    <h1 class="mb-6 mt-2 text-3xl font-bold">Inicia sesión</h1>
    <x-errors />
    <x-google-button />
    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
        @csrf
        <div><label for="email">Correo electrónico</label><input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"></div>
        <div><label for="password">Contraseña</label><input id="password" name="password" type="password" required autocomplete="current-password"></div>
        <div class="flex items-center justify-between gap-3 text-sm"><label><input type="checkbox" name="remember" class="rounded text-teal-700"> Recordarme</label><a href="{{ route('password.request') }}" class="text-teal-800">Olvidé mi contraseña</a></div>
        <button class="btn w-full">Iniciar sesión</button>
    </form>
    <p class="mt-6 text-sm text-slate-600">¿Primera vez aquí? <a class="font-semibold text-teal-800" href="{{ route('register') }}">Crear cuenta</a></p>
</div>
@endsection
