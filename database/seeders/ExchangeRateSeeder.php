<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ExchangeRate;
use Carbon\Carbon;

class ExchangeRateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Intentar actualizar desde API
        $rate = ExchangeRate::updateFromApi();
        
        // Si no se pudo actualizar desde API, crear uno manual
        if (!$rate) {
            ExchangeRate::create([
                'date' => Carbon::today(),
                'buy_rate' => 3.70,
                'sell_rate' => 3.75,
                'source' => 'manual',
                'is_active' => true,
            ]);
            
            $this->command->info('⚠️  Tipo de cambio creado manualmente (no se pudo obtener desde API)');
            $this->command->info('💡 Puedes actualizarlo desde: POST /api/exchange-rate/update-api');
        } else {
            $this->command->info('✅ Tipo de cambio actualizado desde API');
        }
    }
}