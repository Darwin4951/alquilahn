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
