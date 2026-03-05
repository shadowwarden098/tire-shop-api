<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🚀 Iniciando seeders...');

        $this->call([
            ServiceSeeder::class,
            ExchangeRateSeeder::class,
        ]);

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
            $this->command->info('✅ Usuario admin creado (admin@tireshop.com / admin123)');
        } else {
            $this->command->info('ℹ️  Usuario admin ya existe, omitiendo...');
        }

        $this->command->info('');
        $this->command->info('✅ Base de datos inicializada correctamente');
        $this->command->info('');
        $this->command->info('📝 Próximos pasos:');
        $this->command->info('   1. Crea productos en: POST /api/products');
        $this->command->info('   2. Crea clientes en: POST /api/customers');
        $this->command->info('   3. Registra ventas en: POST /api/sales');
        $this->command->info('   4. Ve el dashboard en: GET /api/reports/admin-dashboard');
        $this->command->info('');
    }
}