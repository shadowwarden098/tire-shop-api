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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->string('brand'); // Toyota, Honda, etc.
            $table->string('model');
            $table->integer('year');
            $table->string('plate')->unique();
            $table->string('color')->nullable();
            $table->string('tire_size'); // Tamaño de llanta recomendado
            $table->string('vehicle_type')->default('Automóvil'); // Automóvil, Camioneta, SUV, etc.
            $table->integer('mileage')->nullable(); // Kilometraje
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('plate');
            $table->index('customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};