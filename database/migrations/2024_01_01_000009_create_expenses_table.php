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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number')->unique(); // EXP-2024-0001
            $table->string('description');
            $table->string('category'); // compra_inventario, operativo, salarios, servicios, impuestos, alquiler
            $table->decimal('amount_usd', 12, 2)->nullable();
            $table->decimal('amount_pen', 12, 2)->nullable();
            $table->decimal('exchange_rate', 10, 4)->nullable();
            $table->string('payment_method'); // efectivo, tarjeta, transferencia, cheque
            $table->string('payment_status')->default('paid'); // paid, pending
            $table->string('supplier')->nullable(); // Proveedor o beneficiario
            $table->string('invoice_number')->nullable();
            $table->date('expense_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('expense_date');
            $table->index('category');
            $table->index('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};