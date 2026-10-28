@extends('layouts.app')
@section('content')
<div class="panel mx-auto max-w-md"><h1 class="mb-4 text-2xl font-bold">Recuperar contraseña</h1><x-errors />
<form method="POST" action="{{ route('password.email') }}" class="space-y-4">@csrf<label for="email">Correo electrónico</label><input id="email" type="email" name="email" required value="{{ old('email') }}"><button class="btn">Enviar enlace</button></form></div>
@endsection
