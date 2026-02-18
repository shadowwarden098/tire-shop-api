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
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->decimal('buy_rate', 10, 4); // Tipo de cambio compra
            $table->decimal('sell_rate', 10, 4); // Tipo de cambio venta
            $table->string('source')->default('manual'); // manual, sunat, api
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};