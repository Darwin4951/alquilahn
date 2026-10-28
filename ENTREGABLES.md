# C?digo completo ? AlquilaHN

Consulta README.md para instalaci?n, variables, l?mites de gratuidad y estado de verificaci?n. Este documento refleja los archivos de esta entrega.

## 1. Migraciones y modelos

### `database/migrations/0001_01_01_000000_create_users_table.php`

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('google_id')->nullable()->unique();
            $table->text('avatar')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
```

### `database/migrations/2026_10_27_000001_create_properties_table.php`

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->enum('type', ['casa', 'apartamento', 'cuarto']);
            $table->decimal('price', 10, 2);
            $table->string('city')->index();
            $table->string('colonia')->index();
            $table->text('address');
            $table->text('description');
            $table->string('whatsapp', 8);
            $table->boolean('is_available')->default(true);
            $table->json('images');
            $table->timestamps();
            $table->index(['is_available', 'city', 'type', 'price'], 'properties_catalog_index');
        });
    }
    public function down(): void { Schema::dropIfExists('properties'); }
};
```

### `app/Models/User.php`

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
class User extends Authenticatable
{
    use HasFactory, Notifiable;
    protected $fillable = ['name', 'email', 'password', 'google_id', 'avatar', 'email_verified_at'];
    protected $hidden = ['password', 'remember_token', 'google_id'];
    protected function casts(): array { return ['email_verified_at' => 'datetime', 'password' => 'hashed']; }
    public function properties(): HasMany { return $this->hasMany(Property::class); }
}
```

### `app/Models/Property.php`

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Property extends Model
{
    public const TYPES = ['casa' => 'Casa completa', 'apartamento' => 'Apartamento', 'cuarto' => 'Cuarto / Habitación'];
    protected $fillable = ['title', 'type', 'price', 'department', 'city', 'colonia', 'address', 'description', 'whatsapp', 'is_available', 'images'];
    protected function casts(): array { return ['price' => 'decimal:2', 'images' => 'array', 'is_available' => 'boolean']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function whatsappUrl(): string
    {
        return 'https://wa.me/504'.$this->whatsapp.'?text='.rawurlencode('Hola, me interesa el anuncio "'.$this->title.'" en '.$this->colonia.', '.$this->city.'. ¿Sigue disponible? '.route('properties.show', $this));
    }
}
```

## 2. Autenticaci?n Google y manual

### `app/Http/Controllers/Auth/GoogleAuthController.php`

```php
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
```

### `routes/web.php`

```php
<?php
use App\Livewire\ManageProperties;
use App\Livewire\PropertyCatalog;
use App\Models\Property;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SitemapController;
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/sitemaps/locations.xml', [SitemapController::class, 'locations'])->name('sitemap.locations');
Route::get('/sitemaps/properties-{page}.xml', [SitemapController::class, 'properties'])->whereNumber('page')->name('sitemap.properties');
Route::get('/', PropertyCatalog::class)->name('catalog');
Route::get('/mis-anuncios', ManageProperties::class)->middleware('auth')->name('properties.manage');
Route::get('/inmuebles/{property}', fn (Property $property) => view('properties.show', ['property' => $property, 'seo' => \App\Support\Seo::property($property)]))->name('properties.show');
require __DIR__.'/auth.php';
```

### `routes/auth.php`

```php
<?php
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Facades\Route;
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:5,1');
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:5,1')->name('password.store');
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->middleware('throttle:10,1')->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');
});
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');
```

### `config/services.php`

```php
<?php
return ['google' => ['client_id' => env('GOOGLE_CLIENT_ID'), 'client_secret' => env('GOOGLE_CLIENT_SECRET'), 'redirect' => env('GOOGLE_REDIRECT_URI'), 'site_verification' => env('GOOGLE_SITE_VERIFICATION')]];
```

### `app/Http/Requests/Auth/LoginRequest.php`

```php
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
```

### `app/Http/Controllers/Auth/AuthenticatedSessionController.php`

```php
<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class AuthenticatedSessionController extends Controller
{
    public function create() { return view('auth.login'); }
    public function store(LoginRequest $request)
    {
        $request->authenticate();
        $request->session()->regenerate();
        return redirect()->intended(route('properties.manage'));
    }
    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}
```

### `app/Http/Controllers/Auth/RegisteredUserController.php`

```php
<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
class RegisteredUserController extends Controller
{
    public function create() { return view('auth.register'); }
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        $user = User::create($data);
        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();
        return redirect()->route('properties.manage');
    }
}
```

### `app/Http/Controllers/Auth/PasswordResetController.php`

```php
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
```

### `resources/views/components/google-button.blade.php`

```php
@if(config('services.google.client_id'))
<a href="{{ route('google.redirect') }}" class="flex w-full items-center justify-center gap-3 rounded-xl border border-slate-300 bg-white px-5 py-3 font-semibold hover:bg-stone-50">
    <span aria-hidden="true" class="text-xl font-bold text-blue-600">G</span> Continuar con Google
</a>
@endif
```

### `resources/views/auth/login.blade.php`

```php
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
```

### `resources/views/auth/register.blade.php`

```php
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
```

### `resources/views/auth/forgot-password.blade.php`

```php
@extends('layouts.app')
@section('content')
<div class="panel mx-auto max-w-md"><h1 class="mb-4 text-2xl font-bold">Recuperar contraseña</h1><x-errors />
<form method="POST" action="{{ route('password.email') }}" class="space-y-4">@csrf<label for="email">Correo electrónico</label><input id="email" type="email" name="email" required value="{{ old('email') }}"><button class="btn">Enviar enlace</button></form></div>
@endsection
```

### `resources/views/auth/reset-password.blade.php`

```php
@extends('layouts.app')
@section('content')
<div class="panel mx-auto max-w-md"><h1 class="mb-4 text-2xl font-bold">Nueva contraseña</h1><x-errors />
<form method="POST" action="{{ route('password.store') }}" class="space-y-4">@csrf<input type="hidden" name="token" value="{{ $token }}"><div><label for="email">Correo</label><input id="email" type="email" name="email" value="{{ old('email', $email) }}" required></div><div><label for="password">Contraseña</label><input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"></div><div><label for="password_confirmation">Confirma la contraseña</label><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"></div><button class="btn">Guardar contraseña</button></form></div>
@endsection
```

## 3. Cat?logo y filtros Livewire

### `app/Livewire/PropertyCatalog.php`

```php
<?php
namespace App\Livewire;
use App\Models\Property;
use App\Support\Honduras;
use App\Support\Seo;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
class PropertyCatalog extends Component
{
    use WithPagination;
    #[Url(except: '')] public string $department = '';
    #[Url(except: '')] public string $city = '';
    #[Url(except: '')] public string $search = '';
    #[Url(except: '')] public string $type = '';
    #[Url(except: '')] public string $minPrice = '';
    #[Url(except: '')] public string $maxPrice = '';
    #[Url(except: true)] public bool $onlyAvailable = true;
    #[Url(except: '')] public string $q = '';
    public string $keywords = '';
    public array $filters = [];
    private const FILTER_FIELDS = ['department', 'city', 'search', 'type', 'minPrice', 'maxPrice', 'onlyAvailable'];
    public function mount(): void { $this->filters = $this->only(self::FILTER_FIELDS); $this->keywords = $this->q; }
    public function searchKeywords(): void
    {
        $this->validate(['keywords' => ['nullable', 'string', 'max:150']]);
        $keywords = trim($this->keywords);
        // A new general search starts independently of the sidebar filters.
        $this->clearFilters();
        $this->q = $this->keywords = $keywords;
    }
    public function updatedFilters($value, $key): void
    {
        if ($key === 'department') { $this->filters['city'] = ''; }
    }
    public function searchProperties(): void
    {
        $this->resetValidation();
        $data = validator($this->filters, $this->filterRules($this->filters))->validate();
        foreach (self::FILTER_FIELDS as $field) { $this->$field = $data[$field]; }
        $this->resetPage();
    }
    public function searchAll(): void
    {
        $this->resetValidation();
        $data = validator(array_merge($this->filters, ['keywords' => $this->keywords]), array_merge(
            $this->filterRules($this->filters), ['keywords' => ['nullable', 'string', 'max:150']]
        ))->validate();
        foreach (self::FILTER_FIELDS as $field) { $this->$field = $data[$field]; }
        $this->q = trim($data['keywords']);
        $this->resetPage();
    }
    public function updated($name): void { if ($name === 'department') { $this->city = ''; } $this->resetPage(); }
    public function clearFilters(): void
    {
        $this->reset('department', 'city', 'search', 'type', 'minPrice', 'maxPrice', 'onlyAvailable', 'q', 'keywords');
        $this->filters = $this->only(self::FILTER_FIELDS);
        $this->resetValidation();
        $this->resetPage();
    }
    protected function filterRules(array $values): array
    {
        $rules = ['city' => ['nullable', 'string', 'max:100'], 'search' => ['nullable', 'string', 'max:150'], 'type' => ['nullable', Rule::in(array_keys(Property::TYPES))], 'minPrice' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'], 'maxPrice' => ['nullable', 'numeric', 'min:0', 'max:99999999.99']];
        if (($values['minPrice'] ?? '') !== '' && is_numeric($values['minPrice'] ?? null)) { $rules['maxPrice'][] = 'gte:minPrice'; }
        $rules['department'] = ['nullable', Rule::in(Honduras::departments())];
        $rules['city'] = ['nullable', Rule::in(Honduras::cities((string) ($values['department'] ?? '')))];
        $rules['onlyAvailable'] = ['required', 'boolean'];
        return $rules;
    }
    public function render()
    {
        $values = $this->only(self::FILTER_FIELDS);
        $values['q'] = $this->q;
        $validator = validator($values, array_merge($this->filterRules($values), ['q' => ['nullable', 'string', 'max:150']]));
        if ($validator->fails()) { $this->setErrorBag($validator->errors()); }
        $query = Property::query()->select(['id', 'title', 'type', 'price', 'department', 'city', 'colonia', 'address', 'description', 'whatsapp', 'is_available', 'images', 'created_at', 'updated_at']);
        if ($validator->fails()) { $query->whereRaw('1 = 0'); }
        else {
            $query->when($this->onlyAvailable, fn ($q) => $q->where('is_available', true))
                ->when($this->department !== '', fn ($q) => $q->where('department', $this->department))
                ->when($this->city !== '', fn ($q) => $q->where('city', $this->city))
                ->when($this->type !== '', fn ($q) => $q->where('type', $this->type))
                ->when($this->minPrice !== '', fn ($q) => $q->where('price', '>=', $this->minPrice))
                ->when($this->maxPrice !== '', fn ($q) => $q->where('price', '<=', $this->maxPrice));
            $words = preg_split('/\s+/u', trim($this->q), -1, PREG_SPLIT_NO_EMPTY);
            $ignored = ['en', 'de', 'del', 'la', 'el', 'los', 'las', 'un', 'una', 'y', 'cerca', 'alquiler', 'alquilar', 'renta', 'rentar'];
            $typeWords = ['casa' => 'casa', 'casas' => 'casa', 'apartamento' => 'apartamento', 'apartamentos' => 'apartamento', 'apto' => 'apartamento', 'aptos' => 'apartamento', 'cuarto' => 'cuarto', 'cuartos' => 'cuarto', 'habitacion' => 'cuarto', 'habitaciones' => 'cuarto'];
            foreach (array_unique($words ?: []) as $word) {
                $normalized = Str::lower(Str::ascii($word));
                if (in_array($normalized, $ignored, true)) { continue; }
                $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], Str::lower($word)).'%';
                // Every word must match, but words may occur in different fields.
                $query->where(function ($match) use ($term, $normalized, $typeWords) {
                    foreach (['title', 'description', 'colonia', 'address', 'city', 'department'] as $column) {
                        $match->orWhereRaw("LOWER($column) LIKE ? ESCAPE '!'", [$term]);
                    }
                    if (isset($typeWords[$normalized])) { $match->orWhere('type', $typeWords[$normalized]); }
                });
            }
            if (trim($this->search) !== '') {
                // Treat SQL LIKE wildcards as literal input; ! is an explicit portable escape.
                $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($this->search)).'%';
                $query->where(fn ($q) => $q->whereRaw("colonia LIKE ? ESCAPE '!'", [$term])->orWhereRaw("address LIKE ? ESCAPE '!'", [$term])->orWhereRaw("title LIKE ? ESCAPE '!'", [$term]));
            }
        }
        $draftDepartment = (string) ($this->filters['department'] ?? '');
        $properties = $query->latest('id')->paginate(12)->appends(Seo::canonicalParameters($values));
        $seo = Seo::catalog($values, $properties, ! $validator->fails());
        $this->dispatch('seo-updated', seo: $seo);
        return view('livewire.property-catalog', ['properties' => $properties, 'seo' => $seo, 'departments' => Honduras::departments(), 'cityGroups' => $draftDepartment === '' ? Honduras::groups() : [$draftDepartment => Honduras::cities($draftDepartment)], 'draftDepartment' => $draftDepartment, 'types' => Property::TYPES])->layout('layouts.app', ['seo' => $seo]);
    }
}
```

### `resources/views/livewire/property-catalog.blade.php`

```php
<div>
    <section aria-labelledby="hero-title" class="relative isolate -mx-4 min-h-[380px] overflow-hidden bg-stone-800 sm:-mx-6 lg:-mx-8">
        <img src="https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?auto=format&amp;fit=crop&amp;w=2000&amp;h=1125&amp;q=85" alt="Arquitectura residencial de referencia para explorar casas en alquiler en Honduras - AlquilaHN" fetchpriority="high" loading="eager" decoding="async" width="2000" height="1125" class="absolute inset-0 -z-20 h-full w-full object-cover object-[center_55%]">
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-stone-950/85 via-stone-950/60 to-transparent"></div>
        <div class="px-6 pb-36 pt-12 sm:px-10 sm:pt-16 lg:px-14 lg:pb-32">
            <p class="mb-4 text-[11px] font-semibold uppercase tracking-[0.22em] text-amber-300">Un lugar para tu siguiente capítulo</p>
            <h1 id="hero-title" class="max-w-2xl text-4xl font-semibold leading-[1.12] tracking-[-0.04em] text-white sm:text-5xl lg:text-[56px]">{{ $seo['heading'] }}</h1>
            <p class="mt-5 max-w-lg text-sm leading-relaxed text-white/80 sm:text-base">El espacio que buscas, en la zona que te gusta.<br class="hidden sm:block"> Alquila directamente con el propietario.</p>
        </div>
    </section>

    <form wire:submit="searchAll" role="search" aria-label="Buscar inmuebles en Honduras" class="relative z-10 mx-0 -mt-20 rounded-2xl border border-stone-200/80 bg-white p-3 shadow-xl shadow-stone-900/10 sm:mx-3 lg:mx-5">
        <div class="grid divide-y divide-stone-200 lg:grid-cols-[minmax(0,2.4fr)_minmax(0,1fr)_minmax(0,1.25fr)_minmax(0,1fr)] lg:divide-x lg:divide-y-0">
            <fieldset class="min-w-0 px-3 pb-4 pt-2 lg:py-2 lg:pr-5">
                <legend class="sr-only">Ubicación y palabras clave</legend>
                <label for="general-search" class="search-label">¿Dónde quieres vivir?</label>
                <div class="mb-2 flex items-center gap-2"><x-catalog-icon name="search" class="!h-4 !w-4 text-stone-400"/><input id="general-search" type="search" wire:model="keywords" maxlength="150" placeholder="Apartamento UNAH, cuarto Kennedy…" class="min-w-0 flex-1 rounded-lg border-0 p-0 text-sm font-medium placeholder-stone-400 focus:ring-0" aria-label="Palabras clave"></div>
                <div class="grid grid-cols-2 gap-2">
                    <div><label for="filter-department" class="sr-only">Departamento</label><select id="filter-department" wire:model.live="filters.department" class="search-control"><option value="">Departamento</option>@foreach($departments as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach</select></div>
                    <div><label for="filter-city" class="sr-only">Ciudad / municipio</label><select id="filter-city" wire:model="filters.city" wire:key="catalog-city-{{ $draftDepartment }}" class="search-control"><option value="">Ciudad / municipio</option>@foreach($cityGroups as $group => $options)<optgroup label="{{ $group }}">@foreach($options as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach</optgroup>@endforeach</select></div>
                    <div class="col-span-2"><label for="filter-search" class="sr-only">Colonia, barrio o dirección</label><input id="filter-search" wire:model="filters.search" maxlength="150" placeholder="Colonia, barrio o dirección" class="search-control"></div>
                </div>
            </fieldset>
            <div class="min-w-0 px-3 py-4 lg:px-4 lg:py-2"><label for="filter-type" class="search-label">Tipo de inmueble</label><select id="filter-type" wire:model="filters.type" class="search-control !bg-white !px-0 !text-sm"><option value="">Cualquier tipo</option>@foreach($types as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select><p class="mt-2 text-xs leading-relaxed text-stone-400">Un espacio a tu medida.</p></div>
            <fieldset class="min-w-0 px-3 py-4 lg:px-4 lg:py-2"><legend class="sr-only">Presupuesto mensual en lempiras</legend><p class="search-label">Presupuesto mensual</p><div class="mb-2 text-xs text-stone-400">Lempiras / mes</div><div class="grid grid-cols-2 gap-2"><input type="number" min="0" step="0.01" wire:model="filters.minPrice" placeholder="Desde" aria-label="Precio mínimo" class="search-control"><input type="number" min="0" step="0.01" wire:model="filters.maxPrice" placeholder="Hasta" aria-label="Precio máximo" class="search-control"></div></fieldset>
            <div class="flex flex-col justify-center gap-3 px-3 py-4 lg:py-2 lg:pl-5">
                <button type="submit" aria-label="Buscar inmuebles" class="flex items-center justify-center gap-2 whitespace-nowrap rounded-xl bg-[#0F382F] px-4 py-3.5 text-sm font-semibold text-white transition-colors hover:bg-[#164a3e] disabled:opacity-60 xl:px-6" wire:loading.attr="disabled" wire:target="searchAll"><x-catalog-icon name="search" class="!h-4 !w-4"/><span wire:loading.remove wire:target="searchAll">Buscar</span><span wire:loading wire:target="searchAll">Buscando…</span></button>
                <label class="flex cursor-pointer items-center justify-center gap-2 text-xs font-medium text-stone-500"><input type="checkbox" wire:model="filters.onlyAvailable" class="h-3.5 w-3.5 rounded border-stone-300 text-[#0F382F] focus:ring-[#0F382F]/20"> Solo disponibles</label>
            </div>
        </div>
        @if($errors->any())<div class="px-3 pt-3"><x-errors /></div>@endif
    </form>

    <nav aria-label="Categorías rápidas" class="mb-9 mt-6 flex items-center gap-6 overflow-x-auto border-b border-stone-200 lg:justify-between lg:gap-4">
        <a href="{{ route('catalog', ['type' => 'casa']) }}" class="category-link {{ $type === 'casa' ? '!border-[#0F382F] !text-[#0F382F]' : '' }}"><x-catalog-icon name="home"/>Casas</a>
        <a href="{{ route('catalog', ['type' => 'apartamento']) }}" class="category-link {{ $type === 'apartamento' ? '!border-[#0F382F] !text-[#0F382F]' : '' }}"><x-catalog-icon name="building"/>Apartamentos</a>
        <a href="{{ route('catalog', ['type' => 'cuarto', 'q' => 'universidad']) }}" class="category-link"><x-catalog-icon name="bed"/>Cuartos universitarios</a>
        <a href="{{ route('catalog', ['q' => 'local comercial']) }}" class="category-link"><x-catalog-icon name="shop"/>Locales comerciales</a>
        <a href="{{ route('catalog', ['q' => 'amueblado']) }}" class="category-link"><x-catalog-icon name="sofa"/>Amueblados</a>
        <a href="{{ route('catalog', ['q' => 'UNAH']) }}" class="category-link"><x-catalog-icon name="university"/>Cerca de UNAH</a>
        <a href="{{ route('catalog', ['city' => 'Tegucigalpa']) }}" class="category-link"><x-catalog-icon name="pin"/>Tegucigalpa</a>
        <a href="{{ route('catalog', ['city' => 'San Pedro Sula']) }}" class="category-link"><x-catalog-icon name="pin"/>San Pedro Sula</a>
    </nav>

    <section aria-labelledby="results-title" class="w-full" wire:loading.class="opacity-60">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3"><div><h2 id="results-title" class="text-2xl font-semibold tracking-tight text-[#18181b]">{{ $onlyAvailable ? 'Espacios disponibles para ti' : 'Espacios para ti' }}</h2><p class="mt-1 text-sm text-stone-500">{{ $q !== '' ? 'Resultados para “'.$q.'”' : 'Encuentra el lugar donde empieza lo que sigue.' }}</p></div><div class="flex items-center gap-4"><span aria-live="polite" class="text-xs font-medium text-stone-500">{{ $properties->total() }} anuncios</span>@if($q !== '' || $department !== '' || $city !== '' || $search !== '' || $type !== '' || $minPrice !== '' || $maxPrice !== '' || !$onlyAvailable)<button type="button" wire:click="clearFilters" class="text-xs font-medium text-[#0F382F] underline decoration-stone-300 underline-offset-4">Restablecer búsqueda</button>@endif</div></div>
        @if($properties->count())
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">@foreach($properties as $property)<div wire:key="property-{{ $property->id }}" class="min-w-0"><x-property-card :property="$property" /></div>@endforeach</div>
            <div class="mt-8">{{ $properties->links() }}</div>
        @else
            <div class="flex flex-col items-start justify-between gap-4 rounded-xl border border-stone-200 bg-white px-5 py-5 sm:flex-row sm:items-center"><div class="flex items-start gap-3"><div class="mt-0.5 text-stone-400"><x-catalog-icon name="search"/></div><div><h3 class="text-sm font-semibold text-stone-800">No encontramos resultados exactos con estos filtros</h3><p class="mt-1 text-xs leading-relaxed text-stone-500">Prueba otra ciudad, cambia las palabras o amplía tu presupuesto.</p></div></div><button type="button" wire:click="clearFilters" class="shrink-0 rounded-lg border border-stone-300 bg-white px-4 py-2.5 text-xs font-semibold text-[#0F382F] transition-colors hover:bg-stone-50">Restablecer búsqueda</button></div>
            <section aria-labelledby="popular-title" class="mt-10">
                <div class="mb-5 flex flex-wrap items-end justify-between gap-3"><div><p class="mb-2 text-[10px] font-semibold uppercase tracking-[0.18em] text-[#D97706]">Tu próxima dirección</p><h2 id="popular-title" class="text-xl font-semibold tracking-tight text-[#18181b]">Explora alquileres por zona en Honduras</h2></div><p class="text-[10px] text-stone-400">Fotografías de referencia · Explora anuncios por zona</p></div>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
                    <x-popular-zone city="Tegucigalpa" zone="Zona Universitaria" :parameters="['city' => 'Tegucigalpa', 'q' => 'UNAH']" photo="photo-1522708323590-d24dbb6b0267"/>
                    <x-popular-zone city="San Pedro Sula" zone="Río de Piedras" :parameters="['city' => 'San Pedro Sula', 'search' => 'Río de Piedras']" photo="photo-1600210492486-724fe5c67fb0"/>
                    <x-popular-zone city="La Ceiba" zone="Barrio El Centro" :parameters="['city' => 'La Ceiba', 'search' => 'El Centro']" photo="photo-1600607687939-ce8a6c25118c"/>
                    <x-popular-zone city="Comayagua" zone="Centro de la ciudad" :parameters="['city' => 'Comayagua', 'search' => 'Centro']" photo="photo-1600566753086-00f18fb6b3ea"/>
                </div>
            </section>
        @endif
    </section>
</div>
```

### `resources/views/components/property-card.blade.php`

```php
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
```

### `resources/views/properties/show.blade.php`

```php
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
```

### `config/honduras.php`

```php
<?php
// Compatibility list; department-aware selectors use App\Support\Honduras.
$regions = require __DIR__.'/honduras-regions.php';
return ['cities' => array_values(array_unique(array_merge(...array_values($regions))))];
```

## 4. Publicaci?n y gesti?n

### `app/Livewire/ManageProperties.php`

```php
<?php
namespace App\Livewire;
use App\Models\Property;
use App\Support\Honduras;
use App\Services\PropertyImages;
use App\Exceptions\ImageStorageNotConfigured;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
class ManageProperties extends Component
{
    use WithFileUploads, WithPagination, AuthorizesRequests;
    #[Locked] public ?int $editingId = null;
    #[Locked] public array $existingImages = [];
    public string $title = '';
    public string $type = 'apartamento';
    public string $price = '';
    public string $department = '';
    public string $city = '';
    public string $colonia = '';
    public string $address = '';
    public string $description = '';
    public string $whatsapp = '';
    public bool $is_available = true;
    public array $photos = [];
    public array $pendingPhotos = [];
    public function updatedPendingPhotos(): void
    {
        $this->resetValidation(['photos', 'pendingPhotos']);
        try {
            $this->validate([
                'pendingPhotos' => ['array', 'max:8'],
                'pendingPhotos.*' => $this->rules()['photos.*'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->pendingPhotos = [];
            $this->dispatch('photos-processed', failed: true);
            throw $exception;
        }
        if (count($this->existingImages) + count($this->photos) + count($this->pendingPhotos) > 8) {
            $this->pendingPhotos = [];
            $this->dispatch('photos-processed', failed: true);
            $this->addError('photos', 'Puedes agregar hasta 8 fotos en total. Quita alguna antes de agregar más.');
            return;
        }
        $this->photos = array_merge($this->photos, $this->pendingPhotos);
        $this->pendingPhotos = [];
        $this->dispatch('photos-processed', failed: false);
    }
    public function removePhoto(int $index): void
    {
        unset($this->photos[$index]);
        $this->photos = array_values($this->photos);
        $this->resetValidation('photos');
    }
    public function mount(): void { abort_unless(Auth::check(), 403); }
    public function updatedDepartment(): void { $this->city = ''; $this->resetValidation('city'); }
    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:8', 'max:255'], 'type' => ['required', Rule::in(array_keys(Property::TYPES))],
            'price' => ['required', 'numeric', 'min:1', 'max:99999999.99', 'decimal:0,2'],
            'department' => ['required', Rule::in(Honduras::departments())],
            'city' => ['required', Rule::in(Honduras::cities($this->department))], 'colonia' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'min:10', 'max:2000'], 'description' => ['required', 'string', 'min:20', 'max:5000'],
            'whatsapp' => ['required', 'regex:/^[2389][0-9]{7}$/'], 'is_available' => ['boolean'],
            'photos' => ['array', 'max:8'], 'photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=12000,max_height=12000'],
        ];
    }
    public function edit(int $id): void
    {
        $property = Auth::user()->properties()->findOrFail($id);
        $this->authorize('update', $property);
        $this->resetForm();
        $this->editingId = $property->id;
        foreach (['title', 'type', 'price', 'city', 'colonia', 'address', 'description', 'whatsapp', 'is_available'] as $field) { $this->$field = $property->$field; }
        $this->department = $property->department ?? Honduras::departmentFor($property->city) ?? '';
        $this->existingImages = $property->images;
        $this->dispatch('edit-property');
    }
    public function removeImage(int $index): void
    {
        if (! $this->editingId) { return; }
        $this->authorize('update', Auth::user()->properties()->findOrFail($this->editingId));
        unset($this->existingImages[$index]);
        $this->existingImages = array_values($this->existingImages);
    }
    public function save(PropertyImages $images): void
    {
        abort_unless(Auth::check(), 403);
        $data = $this->validate();
        if (count($this->existingImages) + count($this->photos) < 1 || count($this->existingImages) + count($this->photos) > 8) {
            $this->addError('photos', 'El anuncio debe tener entre 1 y 8 fotos en total.'); return;
        }
        $property = $this->editingId ? Auth::user()->properties()->findOrFail($this->editingId) : null;
        if ($property) { $this->authorize('update', $property); }
        $oldImages = $property?->images ?? [];
        $uploaded = [];
        try {
            foreach ($this->photos as $photo) { $uploaded[] = $images->upload($photo); }
            unset($data['photos']);
            $data['images'] = array_merge($this->existingImages, $uploaded);
            DB::transaction(function () use ($property, $data) {
                if ($property) { $property->update($data); }
                else { Auth::user()->properties()->create($data); }
            });
        } catch (\Throwable $exception) {
            $images->cleanup($uploaded);
            report($exception);
            if ($exception instanceof ImageStorageNotConfigured) {
                $this->addError('photos', app()->isLocal()
                    ? 'Falta configurar Cloudinary. Completa CLOUDINARY_URL en el archivo .env y ejecuta php artisan config:clear. Tus cambios siguen aquí.'
                    : 'El servicio de fotos no está configurado. Contacta al administrador del sitio. Tus cambios siguen aquí.');
            } else {
                $this->addError('photos', 'No se pudo guardar el anuncio. Tus cambios siguen aquí; inténtalo de nuevo.');
            }
            return;
        }
        $images->cleanup(array_values(array_diff($oldImages, $data['images'])));
        $this->resetForm();
        $this->resetPage();
        session()->flash('status', 'Anuncio guardado.');
    }
    public function toggleAvailability(int $id): void
    {
        $property = Auth::user()->properties()->findOrFail($id);
        $this->authorize('update', $property);
        // Atomic SQL flip avoids a read/modify/write race on repeated clicks.
        $property->newQuery()->whereKey($id)->where('user_id', Auth::id())->update(['is_available' => DB::raw('NOT is_available'), 'updated_at' => now()]);
        if ($this->editingId === $id) { $this->is_available = $property->fresh()->is_available; }
    }
    public function delete(int $id, PropertyImages $images): void
    {
        $property = Auth::user()->properties()->findOrFail($id);
        $this->authorize('delete', $property);
        $urls = $property->images;
        $property->delete();
        $images->cleanup($urls);
        if ($this->editingId === $id) { $this->resetForm(); }
        $this->resetPage();
        session()->flash('status', 'Anuncio eliminado.');
    }
    public function resetForm(): void
    {
        $this->reset('editingId', 'existingImages', 'title', 'type', 'price', 'department', 'city', 'colonia', 'address', 'description', 'whatsapp', 'is_available', 'photos', 'pendingPhotos');
        $this->dispatch('photos-processed', failed: false);
        $this->resetValidation();
    }
    public function render()
    {
        return view('livewire.manage-properties', ['properties' => Auth::user()->properties()->latest('id')->paginate(8), 'departments' => Honduras::departments(), 'cities' => $this->department === '' ? [] : Honduras::cities($this->department), 'types' => Property::TYPES])->layout('layouts.app');
    }
}
```

### `resources/views/livewire/manage-properties.blade.php`

```php
<div x-data="{ uploading: false, uploadError: false }" x-on:edit-property.window="document.getElementById('property-form').scrollIntoView({behavior:'smooth'})" x-on:livewire-upload-start="uploading = true; uploadError = false" x-on:livewire-upload-finish="uploading = false" x-on:livewire-upload-cancel="uploading = false" x-on:livewire-upload-error="uploading = false; uploadError = true" x-on:photos-processed="uploading = false; uploadError = $event.detail.failed">
    <p class="text-sm font-semibold text-teal-800">Tu panel de propietario</p><h1 class="mb-8 mt-2 text-3xl font-bold">Mis anuncios</h1>
    <div class="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <section id="property-form" class="panel">
            <h2 class="mb-5 text-xl font-bold">{{ $editingId ? 'Editar anuncio' : 'Publicar un inmueble' }}</h2>
            <x-errors />
            <form wire:submit="save" class="space-y-4">
                <div><label for="title">Título del anuncio</label><input id="title" wire:model="title" required maxlength="255" placeholder="Apartamento de 2 habitaciones en Kennedy"></div>
                <div class="grid gap-4 sm:grid-cols-2"><div><label for="type">Tipo</label><select id="type" wire:model="type">@foreach($types as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div><div><label for="price">Precio mensual (L.)</label><input id="price" wire:model="price" type="number" min="1" max="99999999.99" step="0.01" required></div></div>
                <div><label for="department">Departamento</label><select id="department" wire:model.live="department" required><option value="">Selecciona un departamento</option>@foreach($departments as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach</select></div>
                <div><label for="city">Ciudad o municipio</label><select id="city" wire:model="city" wire:key="manage-city-{{ $department }}-{{ $editingId }}" required @disabled($department === '')><option value="">{{ $department === '' ? 'Selecciona primero el departamento' : 'Selecciona una ciudad o municipio' }}</option>@foreach($cities as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach</select></div>
                <div><label for="colonia">Colonia, barrio, aldea o residencial</label><input id="colonia" wire:model="colonia" required maxlength="255" placeholder="Col. Kennedy"></div>
                <div><label for="address">Dirección y referencias</label><textarea id="address" wire:model="address" rows="2" required maxlength="2000" placeholder="Bloque, calle, punto de referencia…"></textarea></div>
                <div><label for="description">Descripción y servicios incluidos</label><textarea id="description" wire:model="description" rows="4" required maxlength="5000" placeholder="Cuéntanos sobre agua, luz, internet, parqueo, baño y acceso…"></textarea></div>
                <div><label for="whatsapp">WhatsApp del propietario</label><div class="flex items-center gap-2"><span class="text-slate-500">+504</span><input id="whatsapp" wire:model="whatsapp" type="tel" inputmode="numeric" pattern="[2389][0-9]{7}" maxlength="8" required placeholder="98765432"></div></div>
                <label class="flex items-center gap-3"><input type="checkbox" wire:model="is_available" class="rounded text-teal-800"> Inmueble disponible</label>
                @if($existingImages)<div class="grid grid-cols-3 gap-3">@foreach($existingImages as $index => $url)<div wire:key="existing-{{ $editingId }}-{{ md5($url) }}"><img loading="lazy" decoding="async" src="{{ $url }}" class="h-24 w-full rounded-lg object-cover" alt="Foto actual {{ $index + 1 }}"><button type="button" wire:click="removeImage({{ $index }})" class="mt-1 text-xs text-red-700">Quitar foto {{ $index + 1 }}</button></div>@endforeach</div>@endif
                <div class="rounded-xl border border-stone-200 bg-stone-50 p-4">
                    <div class="mb-3 flex items-center justify-between gap-3"><label for="photos" class="font-semibold">Fotos del inmueble</label><span class="text-xs text-stone-500" aria-live="polite">{{ count($existingImages) + count($photos) }} / 8 fotos</span></div>
                    <div wire:key="property-photos-input"><input id="photos" type="file" wire:model="pendingPhotos" accept="image/jpeg,image/png,image/webp" multiple wire:loading.attr="disabled" wire:target="save" x-bind:disabled="uploading || {{ count($existingImages) + count($photos) >= 8 ? 'true' : 'false' }}" class="block w-full text-xs text-stone-600 file:mr-3 file:rounded-lg file:border-0 file:bg-[#0F382F] file:px-4 file:py-2.5 file:text-xs file:font-semibold file:text-white disabled:opacity-50"></div>
                    <p class="mt-3 text-xs leading-relaxed text-stone-500">Selecciona varias fotos a la vez o vuelve a seleccionar para agregar más. En Windows, usa Ctrl o Shift para elegir varias. JPG, PNG o WebP; hasta 5 MB por foto.</p>
                    @if(count($photos))<div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">@foreach($photos as $index => $photo)<div wire:key="new-photo-{{ $photo->getFilename() }}" class="overflow-hidden rounded-lg border border-stone-200 bg-white"><img loading="lazy" decoding="async" src="{{ $photo->temporaryUrl() }}" alt="Nueva foto {{ $index + 1 }} seleccionada" class="h-28 w-full object-cover"><div class="flex items-center justify-between gap-2 p-2"><span class="truncate text-[10px] text-stone-500">{{ $index + 1 }}. {{ $photo->getClientOriginalName() }}</span><button type="button" wire:click="removePhoto({{ $index }})" wire:loading.attr="disabled" wire:target="save,pendingPhotos" aria-label="Quitar nueva foto {{ $index + 1 }}" class="text-xs font-medium text-red-700">Quitar</button></div></div>@endforeach</div>@endif
                    <p x-cloak x-show="uploading" class="mt-3 text-xs text-teal-800" role="status">Preparando fotos. Espera antes de publicar…</p>
                    <p x-cloak x-show="uploadError" class="mt-3 text-xs text-red-700" role="alert">No se pudo cargar la selección. Comprueba la conexión y el tamaño de las fotos, e inténtalo otra vez.</p>
                </div>
                <div class="flex flex-wrap gap-3"><button class="btn" x-bind:disabled="uploading" wire:loading.attr="disabled" wire:target="save">{{ $editingId ? 'Guardar cambios' : 'Publicar anuncio' }}</button>@if($editingId)<button type="button" wire:click="resetForm" wire:loading.attr="disabled" wire:target="save,pendingPhotos" class="rounded-xl border px-4 py-3 text-sm">Cancelar edición</button>@endif</div>
                <p wire:loading wire:target="save" class="text-sm text-teal-800" role="status">Guardando anuncio y fotos en la nube…</p>
            </form>
        </section>
        <section class="space-y-4" aria-label="Tus publicaciones">
            @forelse($properties as $property)
                <article wire:key="managed-{{ $property->id }}" class="panel">
                    <div class="flex items-start gap-4">@if(count($property->images))<img src="{{ $property->images[0] }}" alt="{{ $property->title }} en alquiler en {{ $property->city }} - AlquilaHN" decoding="async" class="h-20 w-24 rounded-xl object-cover" loading="lazy">@endif<div class="min-w-0"><a href="{{ route('properties.show', $property) }}" class="font-bold">{{ $property->title }}</a><p class="mt-1 text-sm text-slate-500">{{ $property->colonia }} · {{ $property->city }}{{ $property->department ? ', '.$property->department : '' }}</p><p class="mt-1 font-bold text-teal-900">L. {{ number_format((float) $property->price, 0) }} / mes</p></div></div>
                    <div class="mt-5 flex flex-wrap items-center justify-between gap-4 border-t pt-4">
                        <button type="button" role="switch" aria-checked="{{ $property->is_available ? 'true' : 'false' }}" aria-label="Disponibilidad de {{ $property->title }}" wire:click="toggleAvailability({{ $property->id }})" wire:loading.attr="disabled" class="flex items-center gap-2 text-sm font-semibold {{ $property->is_available ? 'text-emerald-800' : 'text-red-800' }}"><span class="flex h-6 w-11 items-center rounded-full px-1 {{ $property->is_available ? 'justify-end bg-emerald-600' : 'justify-start bg-slate-400' }}" aria-hidden="true"><span class="h-4 w-4 rounded-full bg-white"></span></span>{{ $property->is_available ? 'Disponible' : 'No disponible' }}</button>
                        <div class="flex gap-4 text-sm"><button wire:click="edit({{ $property->id }})" class="font-semibold text-teal-800">Editar</button><button wire:click="delete({{ $property->id }})" wire:confirm="¿Eliminar definitivamente este anuncio y sus fotos?" wire:loading.attr="disabled" class="text-red-700">Eliminar</button></div>
                    </div>
                </article>
            @empty<div class="panel py-12 text-center"><h2 class="text-xl font-bold">Tu primer anuncio comienza aquí</h2><p class="mt-3 text-slate-500">Completa el formulario y conecta con personas que buscan un hogar.</p></div>@endforelse
            {{ $properties->links() }}
        </section>
    </div>
</div>
```

### `app/Policies/PropertyPolicy.php`

```php
<?php
namespace App\Policies;
use App\Models\Property;
use App\Models\User;
class PropertyPolicy
{
    public function update(User $user, Property $property): bool { return $user->id === $property->user_id; }
    public function delete(User $user, Property $property): bool { return $user->id === $property->user_id; }
}
```

### `app/Services/PropertyImages.php`

```php
<?php
namespace App\Services;
use Cloudinary\Cloudinary;
use App\Exceptions\ImageStorageNotConfigured;
use Illuminate\Support\Facades\Storage;
class PropertyImages
{
    public function upload($file): string
    {
        $url = config('filesystems.disks.cloudinary.url');
        // Local development can publish without a Cloudinary account.
        if ((! is_string($url) || trim($url) === '') && app()->environment('local')) {
            $path = Storage::disk('public')->putFile('properties', $file);
            return Storage::disk('public')->url($path);
        }
        $credentials = is_string($url) ? parse_url($url) : false;
        if (! $credentials || ($credentials['scheme'] ?? '') !== 'cloudinary'
            || empty($credentials['host']) || empty($credentials['user']) || empty($credentials['pass'])
            || in_array($credentials['user'], ['API_KEY', 'your_api_key'], true)
            || in_array($credentials['pass'], ['API_SECRET', 'your_api_secret'], true)
            || in_array($credentials['host'], ['CLOUD_NAME', 'your_cloud_name'], true)) {
            throw new ImageStorageNotConfigured('El almacenamiento de imágenes no está configurado.');
        }
        $result = app(Cloudinary::class)->uploadApi()->upload($file->getRealPath(), [
            'folder' => 'alquilahn/properties', 'resource_type' => 'image',
            'allowed_formats' => ['jpg', 'jpeg', 'png', 'webp'],
            'transformation' => [['width' => 1800, 'height' => 1800, 'crop' => 'limit']],
        ]);
        $url = $result['secure_url'] ?? null;
        if (! is_string($url) || ! str_starts_with($url, 'https://res.cloudinary.com/')) { throw new \RuntimeException('Cloudinary no devolvió una URL segura.'); }
        return $url;
    }
    public function delete(string $url): void
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && preg_match('~^/storage/(properties/[A-Za-z0-9._-]+)$~', $path, $matches)) {
            Storage::disk('public')->delete($matches[1]);
            return;
        }
        // Derive the ID only from this application's untransformed upload URLs.
        if (parse_url($url, PHP_URL_HOST) !== 'res.cloudinary.com' || ! is_string($path) || ! preg_match('~/image/upload/(?:v\d+/)?(alquilahn/properties/.+)\.[a-zA-Z0-9]+$~', $path, $matches)) { return; }
        app(Cloudinary::class)->uploadApi()->destroy($matches[1], ['resource_type' => 'image', 'invalidate' => true]);
    }
    public function cleanup(array $urls): void
    {
        foreach ($urls as $url) {
            try { $this->delete($url); } catch (\Throwable $exception) { report($exception); }
        }
    }
}
```

### `config/cloudinary.php`

```php
<?php
return [
    'cloud_url' => env('CLOUDINARY_URL'),
    'upload_preset' => env('CLOUDINARY_UPLOAD_PRESET'),
    'notification_url' => env('CLOUDINARY_NOTIFICATION_URL'),
];
```

### `config/filesystems.php`

```php
<?php
return ['default' => 'local', 'disks' => [
    'local' => ['driver' => 'local', 'root' => storage_path('app/private'), 'throw' => true],
    'public' => ['driver' => 'local', 'root' => storage_path('app/public'), 'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage', 'visibility' => 'public', 'throw' => true],
    'cloudinary' => ['driver' => 'cloudinary', 'url' => env('CLOUDINARY_URL'), 'secure' => true],
]];
```

## 5. Render y Docker

### `app/Providers/AppServiceProvider.php`

```php
<?php
namespace App\Providers;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}
    public function boot(): void
    {
        if ($this->app->isProduction()) { URL::forceScheme('https'); }
    }
}
```

### `Dockerfile`

```text
FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package*.json ./
RUN if [ -f package-lock.json ]; then npm ci; else npm install; fi
COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources ./resources
RUN npm run build

FROM php:8.5-apache
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
WORKDIR /var/www/html
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libzip-dev libonig-dev libicu-dev libsqlite3-dev ca-certificates \
    && docker-php-ext-install pdo_mysql pdo_sqlite mbstring intl zip \
    && a2enmod rewrite headers \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\nAllowOverride All\nRequire all granted\n</Directory>\n' > /etc/apache2/conf-available/laravel.conf \
    && a2enconf laravel \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY . .
RUN mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/private \
    && composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
    && chown -R www-data:www-data storage bootstrap/cache
COPY --from=assets /app/public/build ./public/build
COPY docker/php.ini /usr/local/etc/php/conf.d/alquiler.ini
COPY docker/apache-limits.conf /etc/apache2/conf-available/alquiler-limits.conf
RUN a2enconf alquiler-limits
RUN sed -i 's/\r$//' /var/www/html/00-laravel-deploy.sh && chmod +x /var/www/html/00-laravel-deploy.sh
EXPOSE 10000
CMD ["/bin/sh", "/var/www/html/00-laravel-deploy.sh"]
```

### `00-laravel-deploy.sh`

```bash
#!/bin/sh
set -eu
cd /var/www/html
: "${APP_KEY:?Define una APP_KEY persistente en Render antes de desplegar}"
export APP_URL="${APP_URL:-${RENDER_EXTERNAL_URL:-}}"
: "${APP_URL:?Define APP_URL con https}"
if [ -z "${GOOGLE_REDIRECT_URI:-}" ]; then
    export GOOGLE_REDIRECT_URI="${APP_URL%/}/auth/google/callback"
fi
: "${DB_HOST:?Define el host MySQL de Aiven}"
: "${DB_PASSWORD:?Define la contraseña MySQL de Aiven}"
: "${CLOUDINARY_URL:?Define CLOUDINARY_URL}"
test "${DB_CONNECTION:-}" = mysql || { echo 'Producción requiere DB_CONNECTION=mysql'; exit 1; }
case "$APP_URL" in https://*) ;; *) echo 'APP_URL debe comenzar con https://'; exit 1 ;; esac
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/private bootstrap/cache
if [ -n "${AIVEN_CA_CERT_BASE64:-}" ]; then
    mkdir -p /var/www/certs
    printf '%s' "$AIVEN_CA_CERT_BASE64" | base64 -d > /var/www/certs/aiven-ca.pem
    export MYSQL_ATTR_SSL_CA=/var/www/certs/aiven-ca.pem
fi
: "${MYSQL_ATTR_SSL_CA:?Proporciona el certificado CA de Aiven}"
test -r "$MYSQL_ATTR_SSL_CA" || { echo 'No se puede leer el certificado CA'; exit 1; }
php artisan config:cache
# Free instances have no pre-deploy command: migrate on startup, before accepting requests.
php artisan migrate --force --no-interaction
php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache
port="${PORT:-10000}"
case "$port" in ''|*[!0-9]*) echo 'PORT debe ser numérico'; exit 1 ;; esac
sed -i "s/^Listen 80$/Listen $port/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$port>/" /etc/apache2/sites-available/000-default.conf
exec apache2-foreground
```

### `docker/php.ini`

```text
expose_php=Off
display_errors=Off
log_errors=On
error_log=/proc/self/fd/2
upload_max_filesize=6M
post_max_size=48M
max_file_uploads=8
memory_limit=192M
max_execution_time=120
opcache.enable=1
opcache.validate_timestamps=0
```

### `render.yaml`

```text
services:
  - type: web
    name: alquilahn
    runtime: docker
    plan: free
    healthCheckPath: /up
    envVars:
      - key: APP_ENV
        value: production
      - key: APP_DEBUG
        value: "false"
      - key: APP_KEY
        sync: false
      - key: DB_CONNECTION
        value: mysql
      - key: DB_HOST
        sync: false
      - key: DB_PORT
        sync: false
      - key: DB_DATABASE
        value: defaultdb
      - key: DB_USERNAME
        value: avnadmin
      - key: DB_PASSWORD
        sync: false
      - key: AIVEN_CA_CERT_BASE64
        sync: false
      - key: CLOUDINARY_URL
        sync: false
      - key: SESSION_DRIVER
        value: database
      - key: SESSION_SECURE_COOKIE
        value: "true"
      - key: CACHE_STORE
        value: file
      - key: LOG_CHANNEL
        value: stderr
```

### `.env.example`

```text
APP_NAME=AlquilaHN
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000
# URL HTTPS de una imagen 1200x630 para compartir el portal (opcional).
SEO_OG_IMAGE=
DB_CONNECTION=sqlite
# SQLite: omit DB_DATABASE to use database/database.sqlite automatically.
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=false
CACHE_STORE=file
LOG_CHANNEL=stderr
LOG_LEVEL=warning
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback
GOOGLE_SITE_VERIFICATION=
CLOUDINARY_URL=
MAIL_MAILER=log
MAIL_FROM_ADDRESS=hola@example.com
# Production: DB_CONNECTION=mysql; APP_ENV=production; APP_DEBUG=false; SESSION_SECURE_COOKIE=true
# DB_HOST=your-service.aivencloud.com
# DB_PORT=12345
# DB_DATABASE=defaultdb
# DB_USERNAME=avnadmin
# DB_PASSWORD=
# MYSQL_ATTR_SSL_CA=/var/www/certs/aiven-ca.pem
# AIVEN_CA_CERT_BASE64=
```

### `bootstrap/app.php`

```php
<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware) {
        // Render terminates TLS at its ingress. Trust forwarded headers only in production.
        if (env('APP_ENV') === 'production') {
            $middleware->trustProxies(at: '*', headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR | \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST | \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT | \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO);
        }
        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions) {})
    ->create();
```

### `config/database.php`

```php
<?php
return [
    'default' => env('DB_CONNECTION', 'sqlite'),
    'connections' => [
        'sqlite' => ['driver' => 'sqlite', 'url' => env('DB_URL'), 'database' => env('DB_DATABASE', database_path('database.sqlite')), 'prefix' => '', 'foreign_key_constraints' => true, 'busy_timeout' => 5000],
        'mysql' => [
            'driver' => 'mysql', 'url' => env('DB_URL'), 'host' => env('DB_HOST', '127.0.0.1'), 'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'defaultdb'), 'username' => env('DB_USERNAME'), 'password' => env('DB_PASSWORD'),
            'unix_socket' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'prefix_indexes' => true, 'strict' => true,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                \Pdo\Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                \Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT => true,
            ], fn ($value) => $value !== null && $value !== '') : [],
        ],
    ],
    'migrations' => ['table' => 'migrations', 'update_date_on_publish' => true],
];
```

### `config/session.php`

```php
<?php
return [
    'driver' => env('SESSION_DRIVER', 'database'), 'lifetime' => 120, 'expire_on_close' => false, 'encrypt' => false,
    'files' => storage_path('framework/sessions'), 'connection' => null, 'table' => 'sessions', 'store' => null,
    'lottery' => [2, 100], 'cookie' => 'alquilahn_session', 'path' => '/', 'domain' => env('SESSION_DOMAIN'),
    'secure' => env('SESSION_SECURE_COOKIE', true), 'http_only' => true, 'same_site' => 'lax', 'partitioned' => false,
];
```

## Archivos auxiliares de la aplicaci?n

### `composer.json`

```json
{
  "name": "alquiler/honduras",
  "type": "project",
  "license": "proprietary",
  "description": "Portal de alquileres en Honduras",
  "require": {
    "php": "~8.5.12",
    "laravel/framework": "^12.0",
    "laravel/socialite": "^5.0",
    "livewire/livewire": "^3.0",
    "cloudinary-labs/cloudinary-laravel": "^3.0"
  },
  "require-dev": {"laravel/breeze": "^2.0", "fakerphp/faker": "^1.23", "phpunit/phpunit": "^11.0", "mockery/mockery": "^1.6", "nunomaduro/collision": "^8.0"},
  "autoload": {"psr-4": {"App\\": "app/", "Database\\Factories\\": "database/factories/", "Database\\Seeders\\": "database/seeders/"}},
  "autoload-dev": {"psr-4": {"Tests\\": "tests/"}},
  "scripts": {
    "dev": ["Composer\\Config::disableProcessTimeout", "@php scripts/serve.php"],
    "post-autoload-dump": ["@php scripts/prepare-directories.php", "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump", "@php artisan package:discover --ansi"],
    "test": ["@php artisan test"]
  },
  "config": {"optimize-autoloader": true, "preferred-install": "dist", "sort-packages": true},
  "minimum-stability": "stable", "prefer-stable": true
}
```

### `package.json`

```json
{
  "private": true,
  "type": "module",
  "scripts": {"dev": "vite", "build": "vite build"},
  "devDependencies": {"@tailwindcss/forms": "^0.5.9", "autoprefixer": "^10.4.20", "laravel-vite-plugin": "^1.0", "postcss": "^8.4.47", "tailwindcss": "^3.4.14", "vite": "^5.4.10"}
}
```

### `artisan`

```php
#!/usr/bin/env php
<?php
use Illuminate\Foundation\Application;
use Symfony\Component\Console\Input\ArgvInput;
define('LARAVEL_START', microtime(true));
require __DIR__.'/vendor/autoload.php';
/** @var Application $app */
$app = require_once __DIR__.'/bootstrap/app.php';
exit($app->handleCommand(new ArgvInput));
```

### `bootstrap/providers.php`

```php
<?php
return [App\Providers\AppServiceProvider::class];
```

### `public/index.php`

```php
<?php
use Illuminate\Http\Request;
define('LARAVEL_START', microtime(true));
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) { require $maintenance; }
require __DIR__.'/../vendor/autoload.php';
(require_once __DIR__.'/../bootstrap/app.php')->handleRequest(Request::capture());
```

### `public/.htaccess`

```text
<IfModule mod_rewrite.c>
    Options -MultiViews -Indexes
    RewriteEngine On
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

### `app/Http/Controllers/Controller.php`

```php
<?php
namespace App\Http\Controllers;
abstract class Controller {}
```

### `routes/console.php`

```php
<?php
```

### `config/app.php`

```php
<?php
return [
    'name' => env('APP_NAME', 'AlquilaHN'), 'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false), 'url' => env('APP_URL', 'http://localhost:8000'),
    'timezone' => 'America/Tegucigalpa', 'locale' => 'es', 'fallback_locale' => 'en', 'faker_locale' => 'es_ES',
    'cipher' => 'AES-256-CBC', 'key' => env('APP_KEY'), 'previous_keys' => [],
    'maintenance' => ['driver' => 'file'],
];
```

### `config/auth.php`

```php
<?php
return [
    'defaults' => ['guard' => 'web', 'passwords' => 'users'],
    'guards' => ['web' => ['driver' => 'session', 'provider' => 'users']],
    'providers' => ['users' => ['driver' => 'eloquent', 'model' => App\Models\User::class]],
    'passwords' => ['users' => ['provider' => 'users', 'table' => 'password_reset_tokens', 'expire' => 60, 'throttle' => 60]],
    'password_timeout' => 10800,
];
```

### `config/cache.php`

```php
<?php
return ['default' => env('CACHE_STORE', 'file'), 'stores' => [
    'file' => ['driver' => 'file', 'path' => storage_path('framework/cache/data'), 'lock_path' => storage_path('framework/cache/data')],
    'array' => ['driver' => 'array', 'serialize' => false],
], 'prefix' => 'alquilahn_'];
```

### `config/logging.php`

```php
<?php
return ['default' => env('LOG_CHANNEL', 'stderr'), 'channels' => [
    'stderr' => ['driver' => 'monolog', 'handler' => Monolog\Handler\StreamHandler::class, 'with' => ['stream' => 'php://stderr'], 'level' => env('LOG_LEVEL', 'warning')],
]];
```

### `config/mail.php`

```php
<?php
return ['default' => env('MAIL_MAILER', 'log'), 'mailers' => [
    'log' => ['transport' => 'log'],
    'smtp' => ['transport' => 'smtp', 'host' => env('MAIL_HOST'), 'port' => env('MAIL_PORT', 587), 'encryption' => env('MAIL_ENCRYPTION', 'tls'), 'username' => env('MAIL_USERNAME'), 'password' => env('MAIL_PASSWORD')],
], 'from' => ['address' => env('MAIL_FROM_ADDRESS', 'hola@example.com'), 'name' => env('APP_NAME', 'AlquilaHN')]];
```

### `config/queue.php`

```php
<?php
return ['default' => 'sync', 'connections' => ['sync' => ['driver' => 'sync']]];
```

### `vite.config.js`

```javascript
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
export default defineConfig({ plugins: [laravel({ input: ['resources/css/app.css', 'resources/js/app.js'], refresh: true })] });
```

### `tailwind.config.js`

```javascript
import forms from '@tailwindcss/forms';
export default { content: ['./resources/**/*.blade.php', './app/Livewire/**/*.php', './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php'], theme: { extend: {} }, plugins: [forms] };
```

### `postcss.config.js`

```javascript
export default { plugins: { tailwindcss: {}, autoprefixer: {} } };
```

### `resources/css/app.css`

```text
@tailwind base;
@tailwind components;
@tailwind utilities;
@layer base {
    body { @apply bg-[#FAF9F6] text-[#18181b] antialiased; font-family: "Inter", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
    input:not([type=checkbox]):not([type=file]), select, textarea { @apply w-full rounded-lg border-stone-300 focus:border-[#0F382F] focus:ring-[#0F382F]; }
    label { @apply text-sm font-medium text-stone-700; }
    button, a, input, select, textarea { @apply focus-visible:outline-offset-4; }
}
@layer components { .btn { @apply inline-flex items-center justify-center rounded-xl bg-teal-800 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 disabled:opacity-50; } .panel { @apply rounded-2xl border border-stone-200 bg-white p-5 shadow-sm; } }
[x-cloak] { display: none !important; }
@layer components {
    .catalog-field { @apply w-full truncate rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm text-stone-800 outline-none transition-all focus:border-[#0F382F] focus:ring-2 focus:ring-[#0F382F]/15; }
    select.catalog-field { @apply pr-8; }
    .catalog-label { @apply mb-1.5 block text-xs font-semibold uppercase tracking-wider text-stone-500; }
    .search-label { @apply mb-2 block text-[11px] font-semibold uppercase tracking-[0.12em] text-stone-500; }
    .search-control { @apply w-full min-w-0 rounded-lg border-0 bg-stone-50 px-3 py-2 text-xs text-stone-800 placeholder-stone-400 focus:bg-white focus:ring-2 focus:ring-[#0F382F]/20; }
    select.search-control { @apply pr-8; }
    .category-link { @apply flex shrink-0 items-center gap-2.5 border-b-2 border-transparent px-1 py-5 text-xs font-medium text-stone-500 transition-colors hover:border-[#0F382F] hover:text-[#0F382F] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#0F382F]; }
}
```

### `resources/js/app.js`

```javascript
// Livewire 3 loads Alpine; do not start a second Alpine instance.
// Keep metadata in sync with applied filters and pagination, without a reload.
window.addEventListener('seo-updated', (event) => {
    const seo = event.detail?.seo;
    if (!seo || typeof seo.title !== 'string') return;
    document.title = seo.title;
    const values = {
        'meta[name="description"]': seo.description,
        'meta[name="robots"]': seo.robots,
        'meta[property="og:title"]': seo.title,
        'meta[property="og:description"]': seo.description,
        'meta[property="og:image"]': seo.image,
        'meta[property="og:url"]': seo.canonical,
        'meta[name="twitter:title"]': seo.title,
        'meta[name="twitter:description"]': seo.description,
        'meta[name="twitter:image"]': seo.image,
    };
    Object.entries(values).forEach(([selector, content]) => {
        const element = document.head.querySelector(selector);
        if (element) element.setAttribute('content', content);
    });
    document.head.querySelector('link[rel="canonical"]')?.setAttribute('href', seo.canonical);
    const schema = document.getElementById('seo-schema');
    if (schema) schema.textContent = JSON.stringify(seo.schema);
});
```

### `resources/views/layouts/app.blade.php`

```php
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <x-seo-head :seo="$seo ?? \App\Support\Seo::defaults()" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen">
    <a href="#contenido" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[60] focus:rounded-lg focus:bg-white focus:p-3">Ir al contenido</a>
    <header class="sticky top-0 z-50 border-b border-stone-200/80 bg-white/90 backdrop-blur-md">
        <nav class="mx-auto flex min-h-[76px] max-w-7xl flex-wrap items-center justify-between gap-x-6 gap-y-3 px-4 py-4 sm:px-6 lg:px-8" aria-label="Navegación principal">
            <a href="{{ route('catalog') }}" class="flex items-center text-xl font-bold tracking-tight text-[#0F382F]">Alquila<span class="text-[#D97706]">HN</span><span class="mx-3 hidden h-4 w-px bg-stone-300 lg:block" aria-hidden="true"></span><span class="hidden text-xs font-normal tracking-normal text-stone-500 lg:inline">Tu próximo hogar, aquí.</span></a>
            <div class="flex flex-wrap items-center gap-4 sm:gap-6">
                <a href="{{ route('catalog') }}" class="text-sm font-medium text-stone-600 transition-colors hover:text-stone-900">Explorar</a>
                @auth
                    <a href="{{ route('properties.manage') }}" class="rounded-lg bg-[#0F382F] px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-[#164a3e]">Mis anuncios</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="text-sm font-medium text-stone-600 hover:text-stone-900">Salir</button></form>
                @else
                    <a href="{{ route('login') }}" class="text-sm font-medium text-stone-600 transition-colors hover:text-stone-900">Ingresar</a><a class="rounded-lg bg-[#0F382F] px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:bg-[#164a3e]" href="{{ route('register') }}">Publicar inmueble</a>
                @endauth
            </div>
        </nav>
    </header>
    <main id="contenido" class="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
        @if(session('status'))<div role="status" class="mb-6 rounded-xl bg-teal-50 p-4 text-teal-900">{{ session('status') }}</div>@endif
        {{ $slot ?? '' }}
        @yield('content')
    </main>
    <footer class="mx-auto mt-14 max-w-7xl border-t border-stone-200 px-4 pt-9 text-xs text-stone-500 sm:px-6 lg:px-8">
        <x-seo-links />
        <div class="grid gap-5 border-t border-stone-200 py-6 sm:grid-cols-2 sm:items-center lg:grid-cols-3">
            <div><p class="font-semibold text-[#0F382F]">AlquilaHN</p><p class="mt-1 text-stone-400">Alquileres en Honduras</p></div>
            <div>
                <p class="font-semibold text-stone-700">Contacto</p>
                <div class="mt-1 flex flex-col items-start gap-1.5">
                    <a href="mailto:garmendiaddarwin@gmail.com" class="transition-colors hover:text-[#0F382F]">garmendiaddarwin@gmail.com</a>
                    <a href="https://wa.me/50497494465" target="_blank" rel="noopener noreferrer" aria-label="Contactar por WhatsApp al +504 9749-4465" class="transition-colors hover:text-[#0F382F]">WhatsApp: +504 9749-4465</a>
                </div>
            </div>
            <p class="text-stone-500">Contacta directamente con el propietario.</p>
        </div>
    </footer>
    @livewireScripts
</body>
</html>
```

### `resources/views/components/errors.blade.php`

```php
@if($errors->any())
<div role="alert" class="mb-5 rounded-xl bg-red-50 p-4 text-sm text-red-800"><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
```

### `.gitignore`

```text
/vendor/
/node_modules/
/.npm-cache/
/public/build/
/public/hot
/.env
/.env.*.local
/database/*.sqlite*
/storage/*
!/storage/.gitignore
/bootstrap/cache/*
!/bootstrap/cache/.gitignore
/.phpunit.cache/
/certs/
/*.pem
```

### `.dockerignore`

```text
.git
.env
.env.*
!.env.example
node_modules
.npm-cache
vendor
public/build
public/hot
storage
bootstrap/cache/*.php
database/*.sqlite*
certs
.phpunit.cache
```

### `phpunit.xml`

```text
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php" colors="true" cacheDirectory=".phpunit.cache">
    <testsuites><testsuite name="Feature"><directory>tests/Feature</directory></testsuite></testsuites>
    <php><env name="APP_ENV" value="testing" force="true"/><env name="APP_KEY" value="base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY="/><env name="DB_CONNECTION" value="sqlite" force="true"/><env name="DB_DATABASE" value=":memory:" force="true"/><env name="SESSION_DRIVER" value="array"/><env name="CACHE_STORE" value="array"/><env name="MAIL_MAILER" value="log"/></php>
</phpunit>
```

### `tests/TestCase.php`

```php
<?php
namespace Tests;
abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase
{
    protected function setUp(): void { parent::setUp(); $this->withoutVite(); }
}
```

### `tests/Feature/PortalTest.php`

```php
<?php
namespace Tests\Feature;
use App\Livewire\ManageProperties;
use App\Livewire\PropertyCatalog;
use App\Models\Property;
use App\Models\User;
use App\Services\PropertyImages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;
class PortalTest extends TestCase
{
    use RefreshDatabase;
    public function test_missing_cloudinary_configuration_reports_the_cause_and_preserves_the_ad(): void
    {
        config(['filesystems.disks.cloudinary.url' => null]);
        $owner = $this->owner();
        $property = $this->property($owner);
        Livewire::actingAs($owner)->test(ManageProperties::class)->call('edit', $property->id)
            ->set('pendingPhotos', [UploadedFile::fake()->image('photo.jpg')])
            ->set('title', 'Este cambio sigue sin guardarse')->call('save')->assertHasErrors('photos')
            ->assertSee('El servicio de fotos no está configurado')->assertCount('photos', 1);
        $this->assertSame('Apartamento en Kennedy', $property->fresh()->title);
    }
    public function test_photos_accumulate_across_selections_and_publish_all_images(): void
    {
        $owner = $this->owner();
        $urls = [];
        $service = \Mockery::mock(PropertyImages::class);
        foreach (['one', 'two', 'three'] as $name) {
            $url = 'https://res.cloudinary.com/demo/image/upload/v1/alquilahn/properties/'.$name.'.jpg';
            $urls[] = $url;
            $service->shouldReceive('upload')->once()->ordered()->andReturn($url);
        }
        $service->shouldReceive('cleanup')->once()->with([]);
        $this->app->instance(PropertyImages::class, $service);
        $component = Livewire::actingAs($owner)->test(ManageProperties::class)
            ->set('pendingPhotos', [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.jpg')])
            ->assertHasNoErrors()->assertCount('photos', 2)->assertSet('pendingPhotos', [])
            ->assertDispatched('photos-processed', failed: false)->assertSee('wire:key="property-photos-input"', false)
            ->set('pendingPhotos', [UploadedFile::fake()->image('three.jpg')])->assertCount('photos', 3)
            ->set('title', 'Apartamento con varias fotos')->set('price', '4000')->set('department', 'Francisco Morazán')
            ->set('city', 'Tegucigalpa')->set('colonia', 'Col. Kennedy')->set('address', 'Frente al parque central')
            ->set('description', 'Incluye agua, luz y acceso independiente.')->set('whatsapp', '98765432')
            ->call('save')->assertHasNoErrors()->assertCount('photos', 0)->assertCount('pendingPhotos', 0);
        $this->assertSame($urls, $owner->properties()->sole()->images);
    }
    public function test_invalid_photo_selection_preserves_previous_photos_and_can_remove_one(): void
    {
        Livewire::actingAs($this->owner())->test(ManageProperties::class)
            ->set('pendingPhotos', [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.jpg')])
            ->set('pendingPhotos', [UploadedFile::fake()->create('document.pdf', 10, 'application/pdf')])
            ->assertHasErrors('pendingPhotos.0')->assertCount('photos', 2)->assertSet('pendingPhotos', [])
            ->assertDispatched('photos-processed', failed: true)->assertSee('wire:key="property-photos-input"', false)
            ->call('removePhoto', 0)->assertCount('photos', 1)
            ->set('pendingPhotos', [UploadedFile::fake()->image('three.jpg')])->assertHasNoErrors()->assertCount('photos', 2)
            ->call('resetForm')->assertCount('photos', 0);
    }
    public function test_eight_photo_limit_includes_existing_images_during_editing(): void
    {
        $owner = $this->owner();
        $property = $this->property($owner, ['images' => array_fill(0, 7, 'https://res.cloudinary.com/demo/image/upload/v1/sample.jpg')]);
        Livewire::actingAs($owner)->test(ManageProperties::class)->call('edit', $property->id)
            ->set('pendingPhotos', [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.jpg')])
            ->assertHasErrors('photos')->assertCount('photos', 0)->assertCount('existingImages', 7)
            ->assertDispatched('photos-processed', failed: true)
            ->set('pendingPhotos', [UploadedFile::fake()->image('one.jpg')])->assertHasNoErrors()->assertCount('photos', 1)
            ->set('pendingPhotos', [UploadedFile::fake()->image('two.jpg')])->assertHasErrors('photos')->assertCount('photos', 1);
    }
    public function test_unified_search_applies_keywords_and_all_filters_together(): void
    {
        $owner = $this->owner();
        $this->property($owner, ['title' => 'Apartamento UNAH elegido']);
        $this->property($owner, ['title' => 'Apartamento UNAH caro', 'price' => 9000]);
        $this->property($owner, ['title' => 'Apartamento sin referencia', 'description' => 'Agua y luz incluidas en zona residencial.']);
        Livewire::test(PropertyCatalog::class)->set('keywords', 'apartamento UNAH')
            ->set('filters.department', 'Francisco Morazán')->set('filters.city', 'Tegucigalpa')
            ->set('filters.search', 'Kennedy')->set('filters.type', 'apartamento')
            ->set('filters.minPrice', '4000')->set('filters.maxPrice', '5000')->call('searchAll')
            ->assertHasNoErrors()->assertSet('q', 'apartamento UNAH')->assertSet('city', 'Tegucigalpa')
            ->assertSee('Apartamento UNAH elegido')->assertDontSee('Apartamento UNAH caro')->assertDontSee('Apartamento sin referencia')
            ->set('keywords', 'otra búsqueda')->set('filters.maxPrice', '1000')->call('searchAll')
            ->assertHasErrors('maxPrice')->assertSet('q', 'apartamento UNAH')->assertSet('maxPrice', '5000');
    }
    public function test_empty_catalog_shows_popular_zones_and_one_search_form(): void
    {
        $response = $this->get('/')->assertOk()->assertSee('No encontramos resultados exactos con estos filtros')
            ->assertSee('Explora alquileres por zona en Honduras')->assertSee('Zona Universitaria')->assertSee('Río de Piedras')
            ->assertSee('images.unsplash.com', false)->assertDontSee('hero-architecture', false)->assertDontSee('Encuentra tu hogar');
        $this->assertSame(1, substr_count($response->getContent(), 'role="search"'));
        $response->assertSee(route('catalog', ['q' => 'UNAH']), false);
    }
    public function test_general_search_matches_words_across_type_description_and_location(): void
    {
        $owner = $this->owner();
        $this->property($owner, ['title' => 'Espacio luminoso para estudiantes', 'description' => 'A dos cuadras de la UNAH, incluye agua.']);
        $this->property($owner, ['title' => 'Habitación junto a la universidad', 'type' => 'cuarto', 'description' => 'Frente a la UNAH, entrada independiente.']);
        $this->property($owner, ['title' => 'Apartamento lejos del campus', 'description' => 'Zona residencial con agua y luz.']);
        Livewire::test(PropertyCatalog::class)->set('keywords', 'apartamento unah')->call('searchKeywords')
            ->assertHasNoErrors()->assertSet('q', 'apartamento unah')->assertSee('Espacio luminoso para estudiantes')
            ->assertDontSee('Habitación junto a la universidad')->assertDontSee('Apartamento lejos del campus')
            ->set('keywords', 'habitación cerca de UNAH')->call('searchKeywords')->assertSee('Habitación junto a la universidad')
            ->assertDontSee('Espacio luminoso para estudiantes')
            ->set('keywords', 'apto Kennedy')->call('searchKeywords')->assertSee('Apartamento lejos del campus');
    }
    public function test_general_search_is_independent_and_filters_can_refine_it(): void
    {
        $owner = $this->owner();
        $this->property($owner, ['title' => 'Apartamento UNAH de prueba']);
        Livewire::test(PropertyCatalog::class)->set('filters.department', 'Cortés')->set('filters.maxPrice', '1000')
            ->call('searchProperties')->assertDontSee('Apartamento UNAH de prueba')
            ->set('keywords', 'apartamento UNAH')->call('searchKeywords')->assertSet('department', '')->assertSet('maxPrice', '')
            ->assertSee('Apartamento UNAH de prueba')
            ->set('filters.maxPrice', '3000')->call('searchProperties')->assertSet('q', 'apartamento UNAH')->assertDontSee('Apartamento UNAH de prueba');
        Livewire::withQueryParams(['q' => 'apartamento UNAH'])->test(PropertyCatalog::class)
            ->assertSet('keywords', 'apartamento UNAH')->assertSee('Apartamento UNAH de prueba');
    }
    public function test_general_search_escapes_wildcards_and_validates_length(): void
    {
        $this->property($this->owner());
        Livewire::test(PropertyCatalog::class)->set('keywords', '%')->call('searchKeywords')->assertDontSee('Apartamento en Kennedy')
            ->set('keywords', str_repeat('a', 151))->call('searchKeywords')->assertHasErrors('keywords')->assertSet('q', '%')
            ->call('clearFilters')->assertSet('q', '')->assertSet('keywords', '')->assertSee('Apartamento en Kennedy');
    }
    public function test_search_button_applies_draft_filters_and_keeps_results_until_submit(): void
    {
        $owner = $this->owner();
        $this->property($owner, ['title' => 'Apartamento exclusivo Kennedy']);
        $this->property($owner, ['title' => 'Casa frente al mar', 'department' => 'Atlántida', 'city' => 'La Ceiba']);
        Livewire::test(PropertyCatalog::class)->assertSee('Buscar inmuebles')
            ->set('filters.department', 'Francisco Morazán')->set('filters.city', 'Tegucigalpa')
            ->set('filters.search', 'Kennedy')->assertSee('Casa frente al mar')
            ->call('searchProperties')->assertHasNoErrors()->assertSet('city', 'Tegucigalpa')
            ->assertSee('Apartamento exclusivo Kennedy')->assertDontSee('Casa frente al mar')
            ->set('filters.minPrice', '6000')->set('filters.maxPrice', '3000')
            ->call('searchProperties')->assertHasErrors('maxPrice')->assertSet('minPrice', '')
            ->assertSee('Apartamento exclusivo Kennedy')
            ->call('clearFilters')->assertSet('filters.search', '')->assertSet('department', '')->assertSee('Casa frente al mar');
    }
    public function test_search_form_initializes_from_shared_url_and_resets_city_for_new_department(): void
    {
        Livewire::withQueryParams(['department' => 'Cortés', 'city' => 'San Pedro Sula'])->test(PropertyCatalog::class)
            ->assertSet('filters.department', 'Cortés')->assertSet('filters.city', 'San Pedro Sula')
            ->set('filters.department', 'Olancho')->assertSet('filters.city', '')
            ->assertSet('department', 'Cortés')->call('searchProperties')->assertHasNoErrors()->assertSet('department', 'Olancho');
    }
    public function test_geography_has_all_departments_and_298_unique_municipalities_within_departments(): void
    {
        $expectedCounts = [8, 16, 10, 21, 23, 12, 19, 28, 6, 17, 4, 19, 28, 16, 23, 28, 9, 11];
        $regions = config('honduras-regions');
        $this->assertCount(18, $regions);
        $this->assertSame($expectedCounts, array_values(array_map('count', $regions)));
        $this->assertSame(298, array_sum(array_map('count', $regions)));
        foreach ($regions as $municipalities) { $this->assertCount(count($municipalities), array_unique($municipalities)); }
        $this->assertContains('Tegucigalpa', \App\Support\Honduras::cities('Francisco Morazán'));
        $this->assertContains('Comayagüela', \App\Support\Honduras::cities('Francisco Morazán'));
        $this->assertNull(\App\Support\Honduras::departmentFor('Santa Rita'));
    }
    public function test_department_distinguishes_municipalities_with_the_same_name(): void
    {
        $owner = $this->owner();
        $this->property($owner, ['title' => 'Casa Santa Rita Copán', 'department' => 'Copán', 'city' => 'Santa Rita']);
        $this->property($owner, ['title' => 'Casa Santa Rita Yoro', 'department' => 'Yoro', 'city' => 'Santa Rita']);
        Livewire::test(PropertyCatalog::class)->set('department', 'Copán')->set('city', 'Santa Rita')
            ->assertSee('Casa Santa Rita Copán')->assertDontSee('Casa Santa Rita Yoro')
            ->set('department', 'Yoro')->assertSet('city', '')
            ->set('city', 'Santa Rita')->assertSee('Casa Santa Rita Yoro')->assertDontSee('Casa Santa Rita Copán')
            ->call('clearFilters')->assertSet('department', '')->assertSet('city', '');
    }
    public function test_publication_rejects_city_from_another_department_and_resets_city_on_change(): void
    {
        $owner = $this->owner();
        Livewire::actingAs($owner)->test(ManageProperties::class)
            ->set('department', 'Cortés')->set('city', 'Tegucigalpa')->call('save')->assertHasErrors('city')
            ->set('city', 'San Pedro Sula')->set('department', 'Olancho')->assertSet('city', '')
            ->set('department', 'Departamento inventado')->call('save')->assertHasErrors('department');
    }
    private function owner(string $email = 'owner@example.com'): User
    {
        return User::create(['name' => 'Dueño', 'email' => $email, 'password' => 'password123']);
    }
    private function property(User $user, array $attributes = []): Property
    {
        return $user->properties()->create(array_merge(['title' => 'Apartamento en Kennedy', 'type' => 'apartamento', 'price' => '4500.00', 'department' => 'Francisco Morazán', 'city' => 'Tegucigalpa', 'colonia' => 'Col. Kennedy', 'address' => 'Bloque 10, frente al parque central', 'description' => 'Incluye agua, baño privado e internet.', 'whatsapp' => '98765432', 'is_available' => true, 'images' => ['https://res.cloudinary.com/demo/image/upload/v1/alquilahn/properties/test.jpg']], $attributes));
    }
    public function test_visitors_can_browse_and_view_contact_details(): void
    {
        $property = $this->property($this->owner());
        $this->get('/')->assertOk()->assertSee('Col. Kennedy');
        $this->get(route('properties.show', $property))->assertOk()->assertSee('Bloque 10')->assertSee('https://wa.me/50498765432', false);
        $this->get('/mis-anuncios')->assertRedirect('/login');
    }
    public function test_catalog_combines_filters_and_can_include_unavailable(): void
    {
        $owner = $this->owner();
        $this->property($owner);
        $this->property($owner, ['title' => 'Casa alquilada', 'type' => 'casa', 'is_available' => false]);
        $this->property($owner, ['title' => 'Cuarto en La Ceiba', 'type' => 'cuarto', 'department' => 'Atlántida', 'city' => 'La Ceiba', 'price' => 2000]);
        Livewire::test(PropertyCatalog::class)->assertSee('Apartamento en Kennedy')->assertDontSee('Casa alquilada')
            ->set('onlyAvailable', false)->assertSee('Casa alquilada')
            ->set('city', 'Tegucigalpa')->set('search', 'Kennedy')->set('type', 'apartamento')->set('minPrice', '4000')->set('maxPrice', '5000')
            ->assertSee('Apartamento en Kennedy')->assertDontSee('Casa alquilada')->assertDontSee('Cuarto en La Ceiba')
            ->set('maxPrice', '3000')->assertHasErrors('maxPrice');
    }
    public function test_owner_can_toggle_and_edit_without_reuploading(): void
    {
        $owner = $this->owner(); $property = $this->property($owner);
        Livewire::actingAs($owner)->test(ManageProperties::class)->call('toggleAvailability', $property->id)
            ->call('edit', $property->id)->set('title', 'Apartamento renovado en Kennedy')->call('save')->assertHasNoErrors();
        $this->assertFalse($property->fresh()->is_available);
        $this->assertSame('Apartamento renovado en Kennedy', $property->fresh()->title);
    }
    public function test_other_users_cannot_edit_toggle_or_delete_properties(): void
    {
        $owner = $this->owner(); $other = $this->owner('other@example.com'); $property = $this->property($owner);
        foreach (['edit', 'toggleAvailability', 'delete'] as $action) {
            try {
                Livewire::actingAs($other)->test(ManageProperties::class)->call($action, $property->id);
                $this->fail('La operación debe rechazar un anuncio de otro propietario.');
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
                // Livewire tests propagate this exception; HTTP converts it to 404.
                $this->assertSame(Property::class, $exception->getModel());
            }
        }
        $this->assertDatabaseHas('properties', ['id' => $property->id, 'is_available' => true]);
    }
    public function test_creation_requires_at_least_one_photo_and_valid_phone(): void
    {
        $owner = $this->owner();
        Livewire::actingAs($owner)->test(ManageProperties::class)->set('title', 'Cuarto independiente')->set('price', '2500')->set('department', 'El Paraíso')->set('city', 'Danlí')->set('colonia', 'Barrio El Centro')->set('address', 'Frente al parque central')->set('description', 'Incluye agua, luz y baño privado.')->set('whatsapp', '123')->call('save')->assertHasErrors('whatsapp')
            ->set('whatsapp', '98765432')->call('save')->assertHasErrors('photos');
        $this->assertDatabaseCount('properties', 0);
    }
    public function test_cloud_upload_failure_preserves_database_and_cleans_uploaded_images(): void
    {
        $owner = $this->owner(); $property = $this->property($owner);
        $url = 'https://res.cloudinary.com/demo/image/upload/v1/alquilahn/properties/new.jpg';
        $service = \Mockery::mock(PropertyImages::class);
        $service->shouldReceive('upload')->once()->ordered()->andReturn($url);
        $service->shouldReceive('upload')->once()->ordered()->andThrow(new \RuntimeException('Cloud unavailable'));
        $service->shouldReceive('cleanup')->once()->with([$url]);
        $this->app->instance(PropertyImages::class, $service);
        Livewire::actingAs($owner)->test(ManageProperties::class)->call('edit', $property->id)
            ->set('photos', [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.jpg')])
            ->set('title', 'Este cambio no debe guardarse')->call('save')->assertHasErrors('photos');
        $this->assertSame('Apartamento en Kennedy', $property->fresh()->title);
    }
    public function test_manual_registration_login_and_logout(): void
    {
        $this->post('/register', ['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'password123', 'password_confirmation' => 'password123'])->assertRedirect('/mis-anuncios');
        $this->assertAuthenticated();
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->post('/login', ['email' => 'ana@example.com', 'password' => 'password123'])->assertRedirect('/mis-anuncios');
        $this->assertAuthenticated();
    }
    public function test_google_does_not_link_existing_account_by_email(): void
    {
        $owner = $this->owner();
        $google = (new \Laravel\Socialite\Two\User)->map(['id' => 'google-123', 'email' => $owner->email, 'name' => 'Dueño']);
        $google->user = ['email_verified' => true];
        $provider = \Mockery::mock(\Laravel\Socialite\Two\GoogleProvider::class);
        $provider->shouldReceive('user')->once()->andReturn($google);
        \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
        $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest(); $this->assertNull($owner->fresh()->google_id);
    }
}
```

### `.github/workflows/tests.yml`

```text
name: Tests
on: [push, pull_request]
jobs:
  portal:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          extensions: mbstring, pdo_sqlite, pdo_mysql, gd, intl, zip
          coverage: none
      - run: mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/app/private storage/logs
      - run: composer install --prefer-dist --no-interaction
      - uses: actions/setup-node@v4
        with:
          node-version: '22'
      - run: npm install
      - run: npm run build
      - run: php artisan test
```

### `scripts/prepare-directories.php`

```php
<?php

// Composer runs this before Laravel boots, including on a fresh checkout.
$root = dirname(__DIR__);
foreach ([
    'bootstrap/cache',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/app/private',
    'storage/logs',
] as $directory) {
    $path = $root.'/'.$directory;
    if (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path)) {
        throw new RuntimeException('No se pudo crear el directorio: '.$path);
    }
}
```

### `config/honduras-regions.php`

```php
<?php
// Fuente UNAH / INE / AMHON: https://oee.unah.edu.hn/lineas-de-investigaci/desarrollo-economico-con-enfoque-territorial/psmh/
return [
 'Atlántida' => ['La Ceiba', 'El Porvenir', 'Esparta', 'Jutiapa', 'La Masica', 'San Francisco', 'Tela', 'Arizona'],
 'Choluteca' => ['Choluteca', 'Apacilagua', 'Concepción de María', 'Duyure', 'El Corpus', 'El Triunfo', 'Marcovia', 'Morolica', 'Namasigüe', 'Orocuina', 'Pespire', 'San Antonio de Flores', 'San Isidro', 'San José', 'San Marcos de Colón', 'Santa Ana de Yusguare'],
 'Colón' => ['Trujillo', 'Balfate', 'Iriona', 'Limón', 'Sabá', 'Santa Fe', 'Santa Rosa de Aguán', 'Sonaguera', 'Tocoa', 'Bonito Oriental'],
 'Comayagua' => ['Comayagua', 'Ajuterique', 'El Rosario', 'Esquías', 'Humuya', 'La Libertad', 'Lamaní', 'La Trinidad', 'Lejamaní', 'Meámbar', 'Minas de Oro', 'Ojos de Agua', 'San Jerónimo', 'San José de Comayagua', 'San José del Potrero', 'San Luis', 'San Sebastián', 'Siguatepeque', 'Villa de San Antonio', 'Las Lajas', 'Taulabé'],
 'Copán' => ['Santa Rosa de Copán', 'Cabañas', 'Concepción', 'Copán Ruinas', 'Corquín', 'Cucuyagua', 'Dolores', 'Dulce Nombre', 'El Paraíso', 'Florida', 'La Jigua', 'La Unión', 'Nueva Arcadia', 'San Agustín', 'San Antonio', 'San Jerónimo', 'San José', 'San Juan de Opoa', 'San Nicolás', 'San Pedro', 'Santa Rita', 'Trinidad de Copán', 'Veracruz'],
 'Cortés' => ['San Pedro Sula', 'Choloma', 'Omoa', 'Pimienta', 'Potrerillos', 'Puerto Cortés', 'San Antonio de Cortés', 'San Francisco de Yojoa', 'San Manuel', 'Santa Cruz de Yojoa', 'Villanueva', 'La Lima'],
 'El Paraíso' => ['Yuscarán', 'Alauca', 'Danlí', 'El Paraíso', 'Güinope', 'Jacaleapa', 'Liure', 'Morocelí', 'Oropolí', 'Potrerillos', 'San Antonio de Flores', 'San Lucas', 'San Matías', 'Soledad', 'Teupasenti', 'Texíguat', 'Vado Ancho', 'Yauyupe', 'Trojes'],
 'Francisco Morazán' => ['Distrito Central', 'Alubarén', 'Cedros', 'Curarén', 'El Porvenir', 'Guaimaca', 'La Libertad', 'La Venta', 'Lepaterique', 'Maraita', 'Marale', 'Nueva Armenia', 'Ojojona', 'Orica', 'Reitoca', 'Sabanagrande', 'San Antonio de Oriente', 'San Buenaventura', 'San Ignacio', 'Cantarranas', 'San Miguelito', 'Santa Ana', 'Santa Lucía', 'Talanga', 'Tatumbla', 'Valle de Ángeles', 'Villa de San Francisco', 'Vallecillo'],
 'Gracias a Dios' => ['Puerto Lempira', 'Brus Laguna', 'Ahuas', 'Juan Francisco Bulnes', 'Ramón Villeda Morales', 'Wampusirpi'],
 'Intibucá' => ['La Esperanza', 'Camasca', 'Colomoncagua', 'Concepción', 'Dolores', 'Intibucá', 'Jesús de Otoro', 'Magdalena', 'Masaguara', 'San Antonio', 'San Isidro', 'San Juan', 'San Marcos de la Sierra', 'San Miguelito', 'Santa Lucía', 'Yamaranguila', 'San Francisco de Opalaca'],
 'Islas de la Bahía' => ['Roatán', 'Guanaja', 'José Santos Guardiola', 'Utila'],
 'La Paz' => ['La Paz', 'Aguanqueterique', 'Cabañas', 'Cane', 'Chinacla', 'Guajiquiro', 'Lauterique', 'Marcala', 'Mercedes de Oriente', 'Opatoro', 'San Antonio del Norte', 'San José', 'San Juan', 'San Pedro de Tutule', 'Santa Ana', 'Santa Elena', 'Santa María', 'Santiago de Puringla', 'Yarula'],
 'Lempira' => ['Gracias', 'Belén', 'Candelaria', 'Cololaca', 'Erandique', 'Gualcince', 'Guarita', 'La Campa', 'La Iguala', 'Las Flores', 'La Unión', 'La Virtud', 'Lepaera', 'Mapulaca', 'Piraera', 'San Andrés', 'San Francisco', 'San Juan Guarita', 'San Manuel Colohete', 'San Rafael', 'San Sebastián', 'Santa Cruz', 'Talgua', 'Tambla', 'Tomalá', 'Valladolid', 'Virginia', 'San Marcos de Caiquín'],
 'Ocotepeque' => ['Ocotepeque', 'Belén Gualcho', 'Concepción', 'Dolores Merendón', 'Fraternidad', 'La Encarnación', 'La Labor', 'Lucerna', 'Mercedes', 'San Fernando', 'San Francisco del Valle', 'San Jorge', 'San Marcos', 'Santa Fe', 'Sensenti', 'Sinuapa'],
 'Olancho' => ['Juticalpa', 'Campamento', 'Catacamas', 'Concordia', 'Dulce Nombre de Culmí', 'El Rosario', 'Esquipulas del Norte', 'Gualaco', 'Guarizama', 'Guata', 'Guayape', 'Jano', 'La Unión', 'Mangulile', 'Manto', 'Salamá', 'San Esteban', 'San Francisco de Becerra', 'San Francisco de la Paz', 'Santa María del Real', 'Silca', 'Yocón', 'Patuca'],
 'Santa Bárbara' => ['Santa Bárbara', 'Arada', 'Atima', 'Azacualpa', 'Ceguaca', 'Concepción del Norte', 'Concepción del Sur', 'Chinda', 'El Níspero', 'Gualala', 'Ilama', 'Las Vegas', 'Macuelizo', 'Naranjito', 'Nuevo Celilac', 'Nueva Frontera', 'Petoa', 'Protección', 'Quimistán', 'San Francisco de Ojuera', 'San José de Colinas', 'San Luis', 'San Marcos', 'San Nicolás', 'San Pedro Zacapa', 'San Vicente Centenario', 'Santa Rita', 'Trinidad'],
 'Valle' => ['Nacaome', 'Alianza', 'Amapala', 'Aramecina', 'Caridad', 'Goascorán', 'Langue', 'San Francisco de Coray', 'San Lorenzo'],
 'Yoro' => ['Yoro', 'Arenal', 'El Negrito', 'El Progreso', 'Jocón', 'Morazán', 'Olanchito', 'Santa Rita', 'Sulaco', 'Victoria', 'Yorito'],
];
```

### `app/Support/Honduras.php`

```php
<?php
namespace App\Support;
class Honduras
{
    public static function departments(): array { return array_keys(config('honduras-regions')); }
    public static function cities(string $department = ''): array
    {
        if ($department === '') { return array_values(array_unique(array_merge(...array_values(self::groups())))); }
        return array_merge(config('honduras-regions')[$department] ?? [], $department === 'Francisco Morazán' ? ['Tegucigalpa', 'Comayagüela'] : []);
    }
    public static function groups(): array
    {
        $groups = [];
        foreach (self::departments() as $department) { $groups[$department] = self::cities($department); }
        return $groups;
    }
    public static function departmentFor(string $city): ?string
    {
        $matches = array_keys(array_filter(self::groups(), fn ($cities) => in_array($city, $cities, true)));
        return count($matches) === 1 ? $matches[0] : null;
    }
}
```

### `database/migrations/2026_10_28_000001_add_department_to_properties.php`

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\Honduras;
return new class extends Migration {
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('department')->nullable()->index();
            $table->index(['department', 'city', 'is_available'], 'properties_location_index');
        });
        foreach (['La Villa de San Francisco' => 'Villa de San Francisco', 'San Juan de Flores' => 'Cantarranas', 'Villa Nueva' => 'Villanueva'] as $old => $new) {
            DB::table('properties')->where('city', $old)->update(['city' => $new]);
        }
        foreach (Honduras::cities() as $city) {
            if ($department = Honduras::departmentFor($city)) {
                DB::table('properties')->where('city', $city)->whereNull('department')->update(['department' => $department]);
            }
        }
        // Los homónimos deben ser ubicados por el propietario, sin adivinar.
    }
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex('properties_location_index'); $table->dropIndex(['department']); $table->dropColumn('department');
        });
    }
};
```

### `resources/views/components/catalog-icon.blade.php`

```php
@props(['name' => 'home'])
<svg {{ $attributes->class(['h-5 w-5 shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('building')<path d="M5 21V3h14v18M3 21h18M9 7h1m4 0h1M9 11h1m4 0h1M9 15h1m4 0h1M10 21v-3h4v3"/>@break
        @case('bed')<path d="M3 18V7m18 11V7M3 14h18M5 14V9h14v5M7 9V6h10v3M3 18v2m18-2v2"/>@break
        @case('shop')<path d="M4 10v11h16V10M3 10l2-7h14l2 7M3 10c0 3 4 3 4 0 0 3 5 3 5 0 0 3 5 3 5 0 0 3 4 3 4 0M9 21v-7h6v7"/>@break
        @case('sofa')<path d="M5 11V7a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v4M5 14h14M5 11a2 2 0 0 0-4 0v7h22v-7a2 2 0 0 0-4 0v3M5 11v3M4 18v3m16-3v3"/>@break
        @case('university')<path d="m2 9 10-5 10 5-10 5-10-5ZM6 11v6c4 3 8 3 12 0v-6M22 9v8"/>@break
        @case('pin')<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>@break
        @case('search')<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4.5 4.5"/>@break
        @default<path d="m3 10 9-7 9 7M5 9v12h14V9M9 21v-7h6v7"/>
    @endswitch
</svg>
```

### `resources/views/components/popular-zone.blade.php`

```php
@props(['city', 'zone', 'parameters', 'photo'])
<a href="{{ route('catalog', $parameters) }}" class="group relative isolate flex min-h-[250px] items-end overflow-hidden rounded-xl bg-stone-300 p-5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#0F382F]">
    <img src="https://images.unsplash.com/{{ $photo }}?auto=format&amp;fit=crop&amp;w=700&amp;h=500&amp;q=80" alt="Interior de referencia para explorar alquileres en {{ $zone }}, {{ $city }}, Honduras - AlquilaHN" loading="lazy" decoding="async" width="700" height="500" class="absolute inset-0 -z-20 h-full w-full object-cover transition-transform duration-500 group-hover:scale-105">
    <div class="absolute inset-0 -z-10 bg-gradient-to-t from-stone-950/85 via-stone-950/10 to-transparent"></div>
    <div class="flex w-full items-end justify-between gap-3 text-white"><div><p class="text-[10px] font-medium uppercase tracking-[0.15em] text-white/70">{{ $city }}</p><h3 class="mt-1 text-base font-semibold tracking-tight">{{ $zone }}</h3><p class="mt-2 text-xs text-white/80">Explorar la zona</p></div><span aria-hidden="true" class="text-xl text-white/90 transition-transform group-hover:translate-x-1">↗</span></div>
</a>
```

### `scripts/serve.php`

```php
<?php
// Development server with upload limits matching the production container.
// PHP's -d flags cannot be changed with ini_set for upload/post limits.
$root = dirname(__DIR__);
chdir($root.'/public');
$router = $root.'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php';
if (! is_file($router)) {
    fwrite(STDERR, "Ejecuta composer install antes de iniciar el servidor.\n");
    exit(1);
}
$command = escapeshellarg(PHP_BINARY)
    .' -d upload_max_filesize=6M -d post_max_size=48M -d max_file_uploads=8'
    .' -S 127.0.0.1:8000 '.escapeshellarg($router);
echo "AlquilaHN: http://127.0.0.1:8000 (hasta 8 fotos, 5 MB por foto).\n";
passthru($command, $status);
exit($status);
```

### `config/seo.php`

```php
<?php
return [
    'title' => 'AlquilaHN | Alquiler de Casas, Apartamentos y Cuartos en Honduras',
    'description' => 'Encuentra casas, apartamentos y cuartos en alquiler en Tegucigalpa, San Pedro Sula y Honduras. Trato directo con el propietario sin intermediarios en AlquilaHN.',
    'image' => env('SEO_OG_IMAGE') ?: 'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?auto=format&fit=crop&w=1200&h=630&q=80',
];
```

### `app/Support/Seo.php`

```php
<?php
namespace App\Support;

use App\Models\Property;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class Seo
{
    public static function url(string $path = '/', array $parameters = []): string
    {
        $base = rtrim(config('app.url'), '/').'/'.ltrim($path, '/');
        return $base.($parameters ? '?'.http_build_query($parameters, '', '&', PHP_QUERY_RFC3986) : '');
    }

    public static function canonicalParameters(array $values): array
    {
        $clean = [];
        foreach (['department', 'city', 'type', 'search', 'q', 'minPrice', 'maxPrice', 'onlyAvailable', 'page'] as $key) {
            $value = $values[$key] ?? '';
            if (! is_scalar($value)) { continue; }
            $value = is_bool($value) ? ($value ? '1' : '0') : trim((string) $value);
            if ($value === '') { continue; }
            if ($key === 'department' && ! in_array($value, Honduras::departments(), true)) { continue; }
            if ($key === 'city' && ! in_array($value, Honduras::cities($clean['department'] ?? ''), true)) { continue; }
            if ($key === 'type' && ! array_key_exists($value, Property::TYPES)) { continue; }
            if ($key === 'onlyAvailable') {
                if (in_array($value, ['0', 'false'], true)) { $clean[$key] = '0'; }
                continue;
            }
            if ($key === 'page') {
                if (ctype_digit($value) && (int) $value > 1) { $clean[$key] = (int) $value; }
                continue;
            }
            if (in_array($key, ['minPrice', 'maxPrice'], true)) {
                if (! is_numeric($value) || (float) $value < 0 || (float) $value > 99999999.99) { continue; }
                $value = rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
            }
            $clean[$key] = Str::limit($value, 150, '');
        }
        return $clean;
    }

    private static function base(string $title, string $description, string $canonical, string $robots = 'index, follow'): array
    {
        return ['title' => $title, 'description' => $description, 'canonical' => $canonical,
            'image' => config('seo.image'), 'robots' => $robots, 'schema' => self::graph()];
    }

    public static function defaults(): array
    {
        return self::base(config('seo.title'), config('seo.description'), self::url(request()->getPathInfo()), 'noindex, follow');
    }

    public static function catalog(array $values, LengthAwarePaginator $properties, bool $valid = true): array
    {
        $parameters = self::canonicalParameters(array_merge($values, ['page' => $properties->currentPage()]));
        $city = $parameters['city'] ?? '';
        $department = $parameters['department'] ?? ($city !== '' ? Honduras::departmentFor($city) : null);
        $types = ['casa' => 'Casas', 'apartamento' => 'Apartamentos', 'cuarto' => 'Cuartos'];
        $type = $types[$parameters['type'] ?? ''] ?? null;
        $location = $city !== '' ? $city.($department ? ', '.$department : '') : ($department ?: 'Honduras');
        $title = config('seo.title');
        if ($type || $city !== '' || $department) { $title = 'Alquiler de '.($type ?: 'inmuebles').' en '.$location.' | AlquilaHN'; }
        elseif (! empty($parameters['q']) || ! empty($parameters['search'])) { $title = 'Alquileres: '.Str::limit($parameters['q'] ?? $parameters['search'], 60).' | AlquilaHN'; }
        if ($properties->currentPage() > 1) { $title .= ' · Página '.$properties->currentPage(); }
        $description = config('seo.description');
        if ($type || $city !== '' || $department) {
            $description = self::description('Encuentra '.Str::lower($type ?: 'casas, apartamentos y cuartos').' en alquiler en '.$location.'. Compara precios en lempiras y contacta directamente al propietario, sin intermediarios en AlquilaHN.');
        }
        $seo = self::base($title, $description, self::url('/', $parameters), $valid ? 'index, follow' : 'noindex, follow');
        $seo['heading'] = ($type || $city !== '' || $department) ? 'Alquiler de '.Str::lower($type ?: 'inmuebles').' en '.$location : 'Alquiler de casas, apartamentos y cuartos en Honduras';
        if ($properties->count()) {
            $items = [];
            foreach ($properties as $index => $property) {
                $items[] = ['@type' => 'ListItem', 'position' => $properties->firstItem() + $index,
                    'url' => self::url(route('properties.show', $property, false)), 'item' => self::listing($property)];
            }
            $seo['schema']['@graph'][] = ['@type' => 'ItemList', '@id' => $seo['canonical'].'#listings',
                'url' => $seo['canonical'], 'name' => $seo['heading'], 'numberOfItems' => count($items), 'itemListElement' => $items];
        }
        return $seo;
    }

    public static function property(Property $property): array
    {
        $seo = self::base($property->title.' en '.$property->city.' | AlquilaHN', self::description(
            Property::TYPES[$property->type].' en alquiler en '.$property->colonia.', '.$property->city.'. L. '.number_format((float) $property->price, 2).' al mes. '.$property->description
        ), self::url(route('properties.show', $property, false)));
        $seo['image'] = $property->images[0] ?? config('seo.image');
        $seo['schema']['@graph'][] = self::listing($property);
        return $seo;
    }

    private static function description(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));
        return Str::limit($text, 157, '…');
    }

    public static function graph(): array
    {
        $home = self::url();
        return ['@context' => 'https://schema.org', '@graph' => [
            ['@type' => 'WebSite', '@id' => $home.'#website', 'name' => 'AlquilaHN', 'url' => $home, 'inLanguage' => 'es-HN',
                'publisher' => ['@id' => $home.'#organization'], 'potentialAction' => ['@type' => 'SearchAction',
                    'target' => ['@type' => 'EntryPoint', 'urlTemplate' => $home.'?q={search_term_string}'], 'query-input' => 'required name=search_term_string']],
            ['@type' => 'Organization', '@id' => $home.'#organization', 'name' => 'AlquilaHN', 'url' => $home,
                'description' => 'Portal de alquiler de inmuebles en Honduras con contacto directo con el propietario.',
                'areaServed' => ['@type' => 'Country', 'name' => 'Honduras', 'identifier' => 'HN']],
        ]];
    }

    public static function listing(Property $property): array
    {
        $url = self::url(route('properties.show', $property, false));
        return ['@type' => 'RealEstateListing', '@id' => $url.'#listing', 'url' => $url, 'name' => $property->title,
            'description' => strip_tags($property->description ?? ''), 'image' => $property->images,
            'datePosted' => $property->created_at?->toAtomString(), 'dateModified' => $property->updated_at?->toAtomString(),
            'inLanguage' => 'es-HN', 'isPartOf' => ['@id' => self::url().'#website'],
            'offers' => ['@type' => 'Offer', 'url' => $url, 'price' => $property->price, 'priceCurrency' => 'HNL',
                'businessFunction' => 'http://purl.org/goodrelations/v1#LeaseOut',
                'availability' => 'https://schema.org/'.($property->is_available ? 'InStock' : 'OutOfStock'),
                'priceSpecification' => ['@type' => 'UnitPriceSpecification', 'price' => $property->price, 'priceCurrency' => 'HNL', 'unitText' => 'mes', 'billingDuration' => 'P1M'],
                'itemOffered' => ['@type' => [match ($property->type) { 'casa' => 'House', 'apartamento' => 'Apartment', default => 'Room' }, 'Product'],
                    'name' => $property->title, 'address' => ['@type' => 'PostalAddress',
                        'streetAddress' => $property->colonia.', '.$property->address, 'addressLocality' => $property->city,
                        'addressRegion' => $property->department ?? Honduras::departmentFor($property->city), 'addressCountry' => 'HN']]],
        ];
    }
}
```

### `app/Http/Controllers/SitemapController.php`

```php
<?php
namespace App\Http\Controllers;

use App\Models\Property;
use App\Support\Honduras;
use App\Support\Seo;

class SitemapController extends Controller
{
    private const PAGE_SIZE = 1000;
    public function index()
    {
        $entries = [['loc' => Seo::url('/sitemaps/locations.xml')]];
        $pages = (int) ceil(Property::count() / self::PAGE_SIZE);
        for ($page = 1; $page <= $pages; $page++) { $entries[] = ['loc' => Seo::url('/sitemaps/properties-'.$page.'.xml')]; }
        return $this->xml('sitemapindex', 'sitemap', $entries);
    }
    public function locations()
    {
        $entries = [['loc' => Seo::url()]];
        foreach (array_keys(Property::TYPES) as $type) { $entries[] = ['loc' => Seo::url('/', ['type' => $type])]; }
        foreach (Honduras::groups() as $department => $cities) {
            $entries[] = ['loc' => Seo::url('/', ['department' => $department])];
            foreach ($cities as $city) { $entries[] = ['loc' => Seo::url('/', ['department' => $department, 'city' => $city])]; }
        }
        return $this->xml('urlset', 'url', $entries);
    }
    public function properties(string $page)
    {
        abort_unless(ctype_digit($page) && (int) $page > 0 && (int) $page <= 2147483, 404);
        $properties = Property::orderBy('id')->forPage((int) $page, self::PAGE_SIZE)->get(['id', 'updated_at']);
        abort_if($properties->isEmpty(), 404);
        $entries = $properties->map(fn ($property) => ['loc' => Seo::url(route('properties.show', $property, false)), 'lastmod' => $property->updated_at->toAtomString()])->all();
        return $this->xml('urlset', 'url', $entries);
    }
    public function robots()
    {
        $text = "User-agent: *\nAllow: /\n";
        foreach (['/admin', '/login', '/register', '/logout', '/forgot-password', '/reset-password', '/auth/', '/mis-anuncios', '/livewire/', '/up'] as $path) { $text .= 'Disallow: '.$path."\n"; }
        return response($text."\nSitemap: ".Seo::url('/sitemap.xml')."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
    private function xml(string $root, string $element, array $entries)
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<'.$root.' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($entries as $entry) {
            $xml .= '<'.$element.'>';
            foreach ($entry as $key => $value) { $xml .= '<'.$key.'>'.htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</'.$key.'>'; }
            $xml .= '</'.$element.'>';
        }
        return response($xml.'</'.$root.'>', 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=300']);
    }
}
```

### `resources/views/components/seo-head.blade.php`

```php
@props(['seo'])
<title>{{ $seo['title'] }}</title>
<meta name="description" content="{{ $seo['description'] }}">
<link rel="canonical" href="{{ $seo['canonical'] }}">
<meta name="robots" content="{{ $seo['robots'] }}">
@if(config('services.google.site_verification'))
<meta name="google-site-verification" content="{{ config('services.google.site_verification') }}">
@endif
<meta name="geo.region" content="HN">
<meta name="geo.placename" content="Honduras">
<meta property="og:locale" content="es_HN">
<meta property="og:site_name" content="AlquilaHN">
<meta property="og:type" content="website">
<meta property="og:title" content="{{ $seo['title'] }}">
<meta property="og:description" content="{{ $seo['description'] }}">
<meta property="og:image" content="{{ $seo['image'] }}">
<meta property="og:image:alt" content="AlquilaHN · Alquiler de inmuebles en Honduras">
<meta property="og:url" content="{{ $seo['canonical'] }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seo['title'] }}">
<meta name="twitter:description" content="{{ $seo['description'] }}">
<meta name="twitter:image" content="{{ $seo['image'] }}">
<script id="seo-schema" type="application/ld+json">{!! json_encode($seo['schema'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
```

### `resources/views/components/seo-links.blade.php`

```php
@php
    $groups = [
        'Alquileres por ciudad' => [
            ['Alquileres en Tegucigalpa', ['city' => 'Tegucigalpa']],
            ['Alquileres en San Pedro Sula', ['city' => 'San Pedro Sula']],
            ['Alquileres en La Ceiba', ['city' => 'La Ceiba']],
            ['Alquileres en Choluteca', ['city' => 'Choluteca']],
            ['Alquileres en Comayagua', ['city' => 'Comayagua']],
        ],
        'Zonas populares y universitarias' => [
            ['Cuartos cerca de la UNAH Tegucigalpa', ['city' => 'Tegucigalpa', 'type' => 'cuarto', 'q' => 'UNAH']],
            ['Apartamentos en Colonia Kennedy', ['city' => 'Tegucigalpa', 'type' => 'apartamento', 'search' => 'Kennedy']],
            ['Alquileres en Residencial Las Uvas', ['city' => 'Tegucigalpa', 'search' => 'Las Uvas']],
            ['Apartamentos en Río de Piedras SPS', ['city' => 'San Pedro Sula', 'type' => 'apartamento', 'search' => 'Río de Piedras']],
        ],
        'Encuentra tu tipo de inmueble' => [
            ['Casas en alquiler en Honduras', ['type' => 'casa']],
            ['Apartamentos amueblados', ['type' => 'apartamento', 'q' => 'amueblado']],
            ['Cuartos para estudiantes', ['type' => 'cuarto', 'q' => 'estudiantes']],
            ['Locales comerciales', ['q' => 'local comercial']],
        ],
    ];
@endphp
<nav aria-label="Explora alquileres en Honduras" class="grid gap-8 pb-9 sm:grid-cols-2 lg:grid-cols-3">
    @foreach($groups as $heading => $links)
        <section><h2 class="mb-4 text-xs font-semibold tracking-tight text-stone-800">{{ $heading }}</h2><ul class="space-y-3">@foreach($links as [$label, $parameters])<li><a href="{{ route('catalog', $parameters) }}" class="text-xs leading-relaxed text-stone-500 transition-colors hover:text-[#0F382F] hover:underline">{{ $label }}</a></li>@endforeach</ul></section>
    @endforeach
</nav>
```

### `tests/Feature/SeoTest.php`

```php
<?php
namespace Tests\Feature;

use App\Livewire\PropertyCatalog;
use App\Models\Property;
use App\Models\User;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;
    private function listing(array $overrides = []): Property
    {
        $user = User::firstOrCreate(['email' => 'seo@example.com'], ['name' => 'Propietario', 'password' => 'password123']);
        return $user->properties()->create(array_merge(['title' => 'Apartamento UNAH Kennedy', 'type' => 'apartamento', 'price' => '4500.50',
            'department' => 'Francisco Morazán', 'city' => 'Tegucigalpa', 'colonia' => 'Colonia Kennedy', 'address' => 'Frente a la UNAH, bloque A',
            'description' => 'Apartamento cerca de UNAH, con agua incluida.', 'whatsapp' => '98765432', 'is_available' => true,
            'images' => ['https://res.cloudinary.com/demo/image/upload/v1/alquilahn/properties/seo.jpg']], $overrides));
    }
    private function document(string $html): \DOMDocument
    {
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        return $document;
    }
    private function schema(string $html): array
    {
        $document = $this->document($html);
        return json_decode($document->getElementById('seo-schema')->textContent, true, flags: JSON_THROW_ON_ERROR);
    }
    public function test_home_has_one_heading_canonical_social_metadata_and_safe_schema(): void
    {
        $response = $this->get('/?q=&city=&utm_source=test');
        $response->assertOk()->assertSee('<title>'.config('seo.title').'</title>', false)
            ->assertSee('<link rel="canonical" href="'.Seo::url().'">', false)
            ->assertSee('content="index, follow"', false)->assertSee('content="es_HN"', false)
            ->assertSee('summary_large_image')->assertSee('loading="eager"', false)->assertSee('fetchpriority="high"', false);
        $this->assertSame(1, $this->document($response->getContent())->getElementsByTagName('h1')->length);
        $this->assertGreaterThanOrEqual(150, mb_strlen(config('seo.description')));
        $this->assertLessThanOrEqual(160, mb_strlen(config('seo.description')));
        $graph = $this->schema($response->getContent())['@graph'];
        $this->assertSame('WebSite', $graph[0]['@type']);
        $this->assertSame(Seo::url().'?q={search_term_string}', $graph[0]['potentialAction']['target']['urlTemplate']);
        $this->assertSame('Organization', $graph[1]['@type']);
        $this->assertSame('Honduras', $graph[1]['areaServed']['name']);
    }
    public function test_filtered_pages_have_specific_metadata_and_itemlist_with_hnl_offers(): void
    {
        $property = $this->listing();
        $response = $this->get('/?department=Francisco%20Moraz%C3%A1n&city=Tegucigalpa&type=apartamento&utm_source=test');
        $response->assertOk()->assertSee('<title>Alquiler de Apartamentos en Tegucigalpa, Francisco Morazán | AlquilaHN</title>', false);
        $schema = $this->schema($response->getContent());
        $item = $schema['@graph'][2]['itemListElement'][0]['item'];
        $this->assertSame('RealEstateListing', $item['@type']);
        $this->assertSame('4500.50', $item['offers']['price']);
        $this->assertSame('HNL', $item['offers']['priceCurrency']);
        $this->assertSame('https://schema.org/InStock', $item['offers']['availability']);
        $this->assertSame('HN', $item['offers']['itemOffered']['address']['addressCountry']);
        $this->assertSame(Seo::url(route('properties.show', $property, false)), $item['url']);
        $this->assertStringNotContainsString('utm_source', $schema['@graph'][2]['url']);
    }
    public function test_metadata_updates_with_applied_livewire_filters_and_pagination(): void
    {
        for ($i = 0; $i < 13; $i++) { $this->listing(['title' => 'Apartamento UNAH '.$i]); }
        Livewire::test(PropertyCatalog::class)->set('filters.city', 'Tegucigalpa')->set('filters.type', 'apartamento')->call('searchAll')
            ->assertDispatched('seo-updated', fn ($event, $params) => str_contains($params['seo']['title'], 'Apartamentos en Tegucigalpa'))
            ->call('gotoPage', 2)->assertDispatched('seo-updated', fn ($event, $params) => str_contains($params['seo']['canonical'], 'page=2'));
        $this->assertSame(['q' => 'UNAH', 'onlyAvailable' => '0', 'page' => 2], Seo::canonicalParameters(['q' => 'UNAH', 'onlyAvailable' => false, 'page' => 2, 'garbage' => 'test', 'city' => '']));
    }
    public function test_property_metadata_uses_real_image_and_unavailable_status(): void
    {
        $property = $this->listing(['is_available' => false, 'title' => 'Apartamento </script><script>alert(1)</script>']);
        $response = $this->get(route('properties.show', $property))->assertOk()->assertSee('property="og:image" content="'.$property->images[0].'"', false);
        $schema = $this->schema($response->getContent());
        $listing = $schema['@graph'][2];
        $this->assertSame('https://schema.org/OutOfStock', $listing['offers']['availability']);
        $this->assertSame($property->title, $listing['name']);
        $this->assertSame(1, $this->document($response->getContent())->getElementsByTagName('h1')->length);
        $this->assertStringNotContainsString('</script><script>alert(1)</script>', $response->getContent());
    }
    public function test_sitemaps_are_valid_xml_and_include_locations_and_all_public_listings(): void
    {
        $property = $this->listing(['is_available' => false]);
        $index = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $this->assertNotFalse(simplexml_load_string($index->getContent()));
        $index->assertSee(Seo::url('/sitemaps/locations.xml'), false)->assertSee(Seo::url('/sitemaps/properties-1.xml'), false);
        $locations = $this->get('/sitemaps/locations.xml')->assertOk();
        $this->assertNotFalse(simplexml_load_string($locations->getContent()));
        $locations->assertSee('department=Francisco%20Moraz%C3%A1n&amp;city=Tegucigalpa', false)->assertSee('department=Yoro', false);
        $properties = $this->get('/sitemaps/properties-1.xml')->assertOk();
        $this->assertNotFalse(simplexml_load_string($properties->getContent()));
        $properties->assertSee(Seo::url(route('properties.show', $property, false)), false)->assertSee('<lastmod>', false);
        $this->get('/sitemaps/properties-0.xml')->assertNotFound();
        $this->get('/sitemaps/properties-2.xml')->assertNotFound();
    }
    public function test_private_pages_are_noindex_and_robots_advertises_sitemap(): void
    {
        $this->get('/login')->assertOk()->assertSee('content="noindex, follow"', false);
        $this->get('/robots.txt')->assertOk()->assertSee('Allow: /')->assertSee('Disallow: /mis-anuncios')
            ->assertSee('Disallow: /login')->assertSee('Sitemap: '.Seo::url('/sitemap.xml'), false);
    }
}
```

### `docker/apache-limits.conf`

```text
# Keep Apache concurrency bounded for Render Free (512 MB).
<IfModule mpm_prefork_module>
    StartServers 1
    MinSpareServers 1
    MaxSpareServers 2
    MaxRequestWorkers 3
    MaxConnectionsPerChild 500
</IfModule>
ServerTokens Prod
ServerSignature Off
```

### `app/Exceptions/ImageStorageNotConfigured.php`

```php
<?php
namespace App\Exceptions;

class ImageStorageNotConfigured extends \RuntimeException {}
```
