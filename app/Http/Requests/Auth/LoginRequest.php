<?php
namespace App\Http\Requests\Auth;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class LoginRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['email' => ['required', 'string', 'email'], 'password' => ['required', 'string']]; }
    public function authenticate(): void
    {
        $key = Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            event(new Lockout($this));
            throw ValidationException::withMessages(['email' => 'Demasiados intentos. Espera '.RateLimiter::availableIn($key).' segundos.']);
        }
        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($key);
            throw ValidationException::withMessages(['email' => 'El correo o la contraseña son incorrectos.']);
        }
        RateLimiter::clear($key);
    }
}
