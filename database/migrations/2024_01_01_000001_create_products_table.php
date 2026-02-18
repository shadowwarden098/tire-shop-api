

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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('brand'); // Michelin, Bridgestone, etc.
            $table->string('model');
            $table->string('size'); // Ej: 205/55R16
            $table->string('category'); // Automóvil, Camioneta, Camión, Moto
            $table->decimal('cost_usd', 10, 2); // Costo de compra en USD
            $table->decimal('price_usd', 10, 2); // Precio de venta en USD
            $table->integer('stock')->default(0);
            $table->integer('min_stock')->default(5); // Stock mínimo para alertas
            $table->text('description')->nullable();
            $table->string('sku')->unique()->nullable();
            $table->string('supplier')->nullable(); // Proveedor
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            // Índices para búsquedas rápidas
            $table->index(['brand', 'model']);
            $table->index('size');
            $table->index('category');
            $table->index('stock');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};