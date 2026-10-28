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
