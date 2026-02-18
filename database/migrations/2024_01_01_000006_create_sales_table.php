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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_number')->unique(); // SV-2024-0001
            $table->foreignId('customer_id')->constrained();
            $table->decimal('subtotal_pen', 12, 2);
            $table->decimal('discount_pen', 10, 2)->default(0);
            $table->decimal('tax_pen', 10, 2)->default(0); // IGV 18%
            $table->decimal('total_pen', 12, 2);
            $table->decimal('exchange_rate', 10, 4); // Tipo de cambio usado
            $table->string('payment_method'); // efectivo, tarjeta, transferencia, credito
            $table->string('payment_status')->default('paid'); // paid, pending, partial
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->string('status')->default('completed'); // completed, cancelled, pending
            $table->string('invoice_type')->nullable(); // boleta, factura
            $table->string('invoice_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('sale_date');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('sale_number');
            $table->index('sale_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};