<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Service;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
            [
                'name' => 'Balanceo de Llantas',
                'code' => 'BALANC',
                'description' => 'Balanceo de 4 llantas',
                'price_pen' => 40.00,
                'duration_minutes' => 30,
                'category' => 'Mantenimiento',
            ],
            [
                'name' => 'Alineación y Balanceo',
                'code' => 'ALINBAL',
                'description' => 'Servicio completo de alineación y balanceo',
                'price_pen' => 80.00,
                'duration_minutes' => 60,
                'category' => 'Mantenimiento',
            ],
            [
                'name' => 'Cambio de Llantas',
                'code' => 'CAMBIO',
                'description' => 'Instalación y cambio de llantas',
                'price_pen' => 20.00,
                'duration_minutes' => 20,
                'category' => 'Instalación',
            ],
            [
                'name' => 'Rotación de Llantas',
                'code' => 'ROTAC',
                'description' => 'Rotación de llantas para desgaste uniforme',
                'price_pen' => 30.00,
                'duration_minutes' => 25,
                'category' => 'Mantenimiento',
            ],
            [
                'name' => 'Parcheado de Llanta',
                'code' => 'PARCHE',
                'description' => 'Reparación de llanta con parche',
                'price_pen' => 15.00,
                'duration_minutes' => 15,
                'category' => 'Reparación',
            ],
            [
                'name' => 'Revisión de Presión',
                'code' => 'PRESION',
                'description' => 'Verificación y ajuste de presión de aire',
                'price_pen' => 5.00,
                'duration_minutes' => 10,
                'category' => 'Mantenimiento',
            ],
            [
                'name' => 'Vulcanizado',
                'code' => 'VULCAN',
                'description' => 'Vulcanizado de llanta',
                'price_pen' => 25.00,
                'duration_minutes' => 40,
                'category' => 'Reparación',
            ],
            [
                'name' => 'Cambio de Válvula',
                'code' => 'VALVUL',
                'description' => 'Reemplazo de válvula de aire',
                'price_pen' => 10.00,
                'duration_minutes' => 10,
                'category' => 'Reparación',
            ],
        ];

        foreach ($services as $service) {
            Service::create($service);
        }

        $this->command->info('✅ Servicios creados exitosamente');
    }
}