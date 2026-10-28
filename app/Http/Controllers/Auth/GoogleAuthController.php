<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
class GoogleAuthController extends Controller
{
    public function redirect()
    {
        abort_unless(config('services.google.client_id') && config('services.google.client_secret'), 503, 'Google no está configurado. Puedes usar correo y contraseña.');
        return Socialite::driver('google')->scopes(['openid', 'email', 'profile'])->redirect();
    }
    public function callback(Request $request)
    {
        if ($request->has('error')) { return redirect()->route('login')->withErrors(['email' => 'Se canceló el acceso con Google.']); }
        try {
            // Preserve OAuth state validation: never use stateless() for browser login.
            $google = Socialite::driver('google')->user();
        } catch (InvalidStateException $exception) {
            return redirect()->route('login')->withErrors(['email' => 'La sesión de Google caducó. Inténtalo otra vez.']);
        } catch (\Throwable $exception) {
            report($exception);
            return redirect()->route('login')->withErrors(['email' => 'No pudimos conectar con Google. Inténtalo más tarde.']);
        }
        $verified = filter_var($google->user['email_verified'] ?? $google->user['verified_email'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (! $google->getEmail() || ! $verified) {
            return redirect()->route('login')->withErrors(['email' => 'Google debe proporcionar un correo verificado.']);
        }
        $user = User::where('google_id', $google->getId())->first();
        if (! $user) {
            $email = Str::lower($google->getEmail());
            // Linking solely by email can take over an existing manual account.
            if (User::where('email', $email)->exists()) {
                return redirect()->route('login')->withErrors(['email' => 'Ya existe una cuenta con ese correo. Entra con tu contraseña o restablécela.']);
            }
            $user = User::create(['name' => $google->getName() ?: 'Usuario', 'email' => $email, 'google_id' => $google->getId(), 'avatar' => $google->getAvatar(), 'email_verified_at' => now(), 'password' => null]);
        }
        Auth::login($user);
        $request->session()->regenerate();
        return redirect()->intended(route('properties.manage'));
    }
}
