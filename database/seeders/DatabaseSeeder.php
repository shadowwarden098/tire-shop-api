<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

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
        
        $this->command->info('');
        $this->command->info('✅ Base de datos inicializada correctamente');
        $this->command->info('');
        $this->command->info('📝 Próximos pasos:');
        $this->command->info('   1. Crea productos en: POST /api/products');
        $this->command->info('   2. Crea clientes en: POST /api/customers');
        $this->command->info('   3. Registra ventas en: POST /api/sales');
        $this->command->info('   4. Ve el dashboard en: GET /api/reports/staff-dashboard (o /api/reports/admin-dashboard como admin)');
        $this->command->info('');
    }
}