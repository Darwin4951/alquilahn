<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
class PasswordResetController extends Controller
{
    public function request() { return view('auth.forgot-password'); }
    public function email(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink($request->only('email'));
        return back()->with('status', 'Si existe una cuenta con ese correo, recibirás un enlace para restablecer tu contraseña.');
    }
    public function reset(Request $request, string $token) { return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]); }
    public function update(Request $request)
    {
        $request->validate(['token' => ['required'], 'email' => ['required', 'email'], 'password' => ['required', 'confirmed', PasswordRule::min(8)]]);
        $status = Password::reset($request->only('email', 'password', 'password_confirmation', 'token'), function ($user, $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });
        return $status === Password::PASSWORD_RESET ? redirect()->route('login')->with('status', 'Contraseña actualizada.') : back()->withErrors(['email' => 'El enlace no es válido o ha caducado.']);
    }
}
