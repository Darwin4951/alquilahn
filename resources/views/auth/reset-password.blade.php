@extends('layouts.app')
@section('content')
<div class="panel mx-auto max-w-md"><h1 class="mb-4 text-2xl font-bold">Nueva contraseña</h1><x-errors />
<form method="POST" action="{{ route('password.store') }}" class="space-y-4">@csrf<input type="hidden" name="token" value="{{ $token }}"><div><label for="email">Correo</label><input id="email" type="email" name="email" value="{{ old('email', $email) }}" required></div><div><label for="password">Contraseña</label><input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"></div><div><label for="password_confirmation">Confirma la contraseña</label><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"></div><button class="btn">Guardar contraseña</button></form></div>
@endsection
