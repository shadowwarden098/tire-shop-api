<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique(); // BALANC, ALINEA, CAMBIO, etc.
            $table->text('description')->nullable();
            $table->decimal('price_pen', 10, 2);
            $table->integer('duration_minutes')->nullable(); // Duración estimada
            $table->string('category')->default('Mantenimiento'); // Mantenimiento, Instalación, Reparación
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('code');
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};