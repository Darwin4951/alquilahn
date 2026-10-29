<?php
namespace Tests\Feature;

use App\Livewire\ManageProperties;
use App\Livewire\PropertyCatalog;
use App\Models\Property;
use App\Models\User;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class SalesTest extends TestCase
{
    use RefreshDatabase;
    private function listing(array $values = []): Property
    {
        $owner = User::firstOrCreate(['email' => 'sales@example.com'], ['name' => 'Vendedor', 'password' => 'password123']);
        return $owner->properties()->create(array_merge([
            'title' => 'Anuncio original de alquiler', 'type' => 'casa', 'price' => 5000,
            'department' => 'Francisco Morazán', 'city' => 'Tegucigalpa', 'colonia' => 'Kennedy',
            'address' => 'Frente al parque principal', 'description' => 'Una descripción completa del anuncio original.',
            'whatsapp' => '98765432', 'images' => ['https://example.com/photo.jpg'], 'is_available' => true,
        ], $values));
    }
    public function test_original_rentals_keep_monthly_price_and_safe_migration_defaults(): void
    {
        $p = $this->listing();
        $this->assertSame('alquiler', $p->fresh()->operation);
        $this->get(route('properties.show', $p))->assertOk()->assertSee('/ mes')->assertSee('Alquiler');
        $this->assertSame('P1M', Seo::listing($p)['offers']['priceSpecification']['billingDuration']);
    }
    public function test_owner_can_convert_edit_and_sell_house_without_reuploading(): void
    {
        $p = $this->listing();
        Livewire::actingAs($p->user)->test(ManageProperties::class)->call('edit', $p->id)
            ->set('operation', 'venta')->set('price', '1500000')->call('save')->assertHasNoErrors()
            ->call('markSold', $p->id);
        $p->refresh();
        $this->assertTrue($p->is_sold);
        $this->assertFalse($p->is_available);
        $this->assertSame(['https://example.com/photo.jpg'], $p->images);
        $this->get(route('properties.show', $p))->assertOk()->assertSee('Vendido')->assertDontSee('/ mes');
        $this->assertArrayNotHasKey('priceSpecification', Seo::listing($p)['offers']);
    }
    public function test_land_requires_area_and_search_filters_real_area_and_unit(): void
    {
        $p = $this->listing();
        Livewire::actingAs($p->user)->test(ManageProperties::class)->call('edit', $p->id)
            ->set('type', 'terreno')->assertSet('operation', 'venta')->call('save')->assertHasErrors('area')
            ->set('area', '500')->set('area_unit', 'v2')->set('title', 'Terreno seleccionado')->call('save')->assertHasNoErrors();
        $this->listing(['operation' => 'venta', 'type' => 'terreno', 'title' => 'Terreno de otra unidad', 'area' => 500, 'area_unit' => 'm2']);
        $this->get('/?operation=venta&type=terreno')->assertOk()->assertSee('Terreno seleccionado')->assertSee('Terreno de otra unidad');
        Livewire::test(PropertyCatalog::class)->set('filters.operation', 'venta')->set('filters.type', 'terreno')
            ->set('filters.minArea', '400')->set('filters.maxArea', '600')->set('filters.areaUnit', 'v2')->call('searchAll')
            ->assertHasNoErrors()->assertSee('Terreno seleccionado')->assertDontSee('Terreno de otra unidad');
        $this->get(route('properties.show', $p))->assertOk()->assertSee('500.00')->assertSee('v²');
    }
    public function test_vehicle_validation_details_and_category_switch_clear_irrelevant_fields(): void
    {
        $p = $this->listing();
        $c = Livewire::actingAs($p->user)->test(ManageProperties::class)->call('edit', $p->id)->set('type', 'vehiculo')
            ->call('save')->assertHasErrors(['brand', 'vehicle_model', 'year', 'mileage', 'transmission', 'fuel', 'vehicle_condition']);
        foreach (['vehicle_type' => 'Pickup', 'brand' => 'Toyota', 'vehicle_model' => 'Hilux', 'year' => '2022', 'mileage' => '35000', 'transmission' => 'manual', 'fuel' => 'diesel', 'vehicle_condition' => 'usado', 'colonia' => '', 'address' => ''] as $f => $v) { $c->set($f, $v); }
        $c->call('save')->assertHasNoErrors();
        $this->assertSame('', $p->fresh()->address);
        $this->get(route('properties.show', $p))->assertOk()->assertSee('Toyota')->assertSee('Hilux')->assertSee('35000')->assertDontSee('Dirección y referencias')->assertDontSee('/ mes');
        $this->assertSame('Product', Seo::listing($p->fresh())['@type']);
        $c->call('edit', $p->id)->set('type', 'casa')->set('colonia', 'Kennedy')->set('address', 'Frente al parque principal')->call('save')->assertHasNoErrors();
        $this->assertNull($p->fresh()->brand);
    }
    public function test_vehicle_search_combines_brand_model_year_price_location_and_url(): void
    {
        $p = $this->listing(['operation' => 'venta', 'type' => 'vehiculo', 'title' => 'Pickup elegida', 'brand' => 'Toyota', 'vehicle_model' => 'Hilux', 'year' => 2022, 'price' => 400000]);
        $this->listing(['operation' => 'venta', 'type' => 'vehiculo', 'title' => 'Pickup descartada', 'brand' => 'Ford', 'vehicle_model' => 'Ranger', 'year' => 2010]);
        $params = ['operation' => 'venta', 'type' => 'vehiculo', 'brand' => 'Toyota', 'vehicleModel' => 'Hilux', 'minYear' => '2020', 'maxYear' => '2024', 'minPrice' => '300000', 'maxPrice' => '500000', 'city' => 'Tegucigalpa'];
        $this->get('/?'.http_build_query($params))->assertOk()->assertSee('Pickup elegida')->assertDontSee('Pickup descartada')->assertSee('Vehículos en venta');
        $this->assertSame($params['brand'], Seo::canonicalParameters($params)['brand']);
        Livewire::test(PropertyCatalog::class)->set('filters.minYear', '2024')->set('filters.maxYear', '2020')->call('searchAll')->assertHasErrors('maxYear');
    }
    public function test_sales_permissions_and_operation_constraints(): void
    {
        $p = $this->listing(['operation' => 'venta']);
        $other = User::create(['name' => 'Otro', 'email' => 'other@example.com', 'password' => 'password123']);
        try {
            Livewire::actingAs($other)->test(ManageProperties::class)->call('markSold', $p->id);
            $this->fail('No debe permitir marcar anuncios ajenos.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            $this->assertSame(Property::class, $exception->getModel());
        }
        Livewire::actingAs($p->user)->test(ManageProperties::class)->call('edit', $p->id)->set('type', 'vehiculo')->set('operation', 'alquiler')->call('save')->assertHasErrors('operation');
        $this->assertFalse($p->fresh()->is_sold);
    }
    public function test_migration_preserves_existing_rows_and_can_reverse_when_no_new_categories_exist(): void
    {
        $migration = require database_path('migrations/2026_10_29_000001_add_sales_to_properties.php');
        $migration->down();
        $id = DB::table('properties')->insertGetId(['user_id' => User::create(['name' => 'Original', 'email' => 'original@example.com', 'password' => 'password123'])->id, 'title' => 'Original', 'type' => 'casa', 'price' => 1000, 'city' => 'Tegucigalpa', 'colonia' => 'Centro', 'address' => 'Dirección original', 'description' => 'Texto original', 'whatsapp' => '98765432', 'images' => '["https://example.com/original.jpg"]']);
        $migration->up();
        $p = Property::findOrFail($id);
        $this->assertSame('alquiler', $p->operation);
        $this->assertSame(['https://example.com/original.jpg'], $p->images);
        $this->assertSame('Texto original', $p->description);
        $migration->down();
        $this->assertFalse(Schema::hasColumn('properties', 'operation'));
        $this->assertSame(1, DB::table('properties')->count());
        $migration->up();
    }
    public function test_new_sales_can_be_published_with_photos_for_each_category(): void
    {
        $owner = $this->listing()->user;
        $images = \Mockery::mock(\App\Services\PropertyImages::class);
        $images->shouldReceive('upload')->times(3)->andReturn('https://example.com/new.jpg');
        $images->shouldReceive('cleanup')->times(3)->with([]);
        $this->app->instance(\App\Services\PropertyImages::class, $images);
        foreach (['casa', 'terreno', 'vehiculo'] as $type) {
            $c = Livewire::actingAs($owner)->test(ManageProperties::class)->set('type', $type)->set('operation', 'venta')
                ->set('title', 'Nueva venta de '.$type)->set('price', '200000')->set('department', 'Francisco Morazán')
                ->set('city', 'Tegucigalpa')->set('colonia', 'Centro')->set('address', 'Frente al parque central')
                ->set('description', 'Descripción detallada de la nueva publicación.')->set('whatsapp', '98765432')
                ->set('pendingPhotos', [\Illuminate\Http\UploadedFile::fake()->image('photo.jpg')]);
            if ($type === 'terreno') { $c->set('area', '300')->set('area_unit', 'm2'); }
            if ($type === 'vehiculo') {
                foreach (['vehicle_type' => 'Automóvil', 'brand' => 'Toyota', 'vehicle_model' => 'Corolla', 'year' => '2020', 'mileage' => '30000', 'transmission' => 'automatica', 'fuel' => 'gasolina', 'vehicle_condition' => 'usado'] as $field => $value) { $c->set($field, $value); }
            }
            $c->call('save')->assertHasNoErrors();
            $this->assertDatabaseHas('properties', ['user_id' => $owner->id, 'type' => $type, 'operation' => 'venta']);
        }
    }
    public function test_rollback_refuses_to_discard_sales(): void
    {
        $this->listing(['operation' => 'venta']);
        $migration = require database_path('migrations/2026_10_29_000001_add_sales_to_properties.php');
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }
}
