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
