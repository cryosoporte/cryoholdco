<?php

namespace Database\Seeders;

use App\Models\FuenteCarga;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FuenteCargaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('testing') || DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        FuenteCarga::factory()->create([
            'estado' => FuenteCarga::FALLIDA,
            'ruta_archivo' => null,
            'fecha_fin' => now(),
            'mensaje' => 'Faltan columnas en el archivo: Importe.',
        ]);
    }
}
