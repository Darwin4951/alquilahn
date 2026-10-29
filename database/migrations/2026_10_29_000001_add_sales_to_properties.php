<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('type')->change();
            $table->string('operation', 10)->default('alquiler');
            $table->boolean('is_sold')->default(false);
            $table->decimal('area', 14, 2)->nullable();
            $table->string('area_unit', 4)->nullable();
            foreach (['vehicle_type', 'brand', 'vehicle_model', 'transmission', 'fuel', 'vehicle_condition'] as $field) { $table->string($field, 100)->nullable(); }
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedInteger('mileage')->nullable();
            $table->index(['operation', 'type', 'is_available', 'price'], 'properties_sales_index');
            $table->index(['brand', 'vehicle_model', 'year'], 'properties_vehicle_index');
        });
    }
    public function down(): void
    {
        // Refuse a rollback that would discard sales or category-specific data.
        if (DB::table('properties')->where('operation', 'venta')->orWhereNotIn('type', ['casa', 'apartamento', 'cuarto'])->exists()) {
            throw new RuntimeException('Hay anuncios nuevos. Conserva esta migración o exporta y adapta esos anuncios antes de revertir.');
        }
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex('properties_sales_index');
            $table->dropIndex('properties_vehicle_index');
            $table->dropColumn(['operation', 'is_sold', 'area', 'area_unit', 'vehicle_type', 'brand', 'vehicle_model', 'transmission', 'fuel', 'vehicle_condition', 'year', 'mileage']);
            $table->enum('type', ['casa', 'apartamento', 'cuarto'])->change();
        });
    }
};
