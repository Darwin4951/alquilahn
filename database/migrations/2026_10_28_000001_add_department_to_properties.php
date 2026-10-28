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
