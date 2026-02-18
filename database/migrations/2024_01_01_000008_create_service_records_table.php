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
        Schema::create('service_records', function (Blueprint $table) {
            $table->id();
            $table->string('record_number')->unique(); // SR-2024-0001
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('vehicle_id')->constrained();
            $table->foreignId('service_id')->constrained();
            $table->decimal('price_pen', 10, 2);
            $table->decimal('discount_pen', 10, 2)->default(0);
            $table->decimal('total_pen', 10, 2);
            $table->string('payment_method'); // efectivo, tarjeta, transferencia
            $table->string('status')->default('completed'); // completed, cancelled, in_progress
            $table->text('notes')->nullable();
            $table->text('technician_notes')->nullable(); // Notas del técnico
            $table->integer('mileage')->nullable(); // Kilometraje al momento del servicio
            $table->timestamp('service_date');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            
            $table->index('record_number');
            $table->index('service_date');
            $table->index(['customer_id', 'vehicle_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_records');
    }
};