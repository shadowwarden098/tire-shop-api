<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Agregar columna role si no existe
        if (!Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['admin', 'employee'])->default('employee')->after('email');
                $table->boolean('is_active')->default(true)->after('role');
            });
        }

        // Crear usuario admin por defecto si no existe
        if (DB::table('users')->where('email', 'admin@tireshop.com')->doesntExist()) {
            DB::table('users')->insert([
                'name'       => 'Administrador',
                'email'      => 'admin@tireshop.com',
                'password'   => Hash::make('admin123'),
                'role'       => 'admin',
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_active']);
        });
    }
};
