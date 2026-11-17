<?php

namespace Database\Factories;

use App\Models\FuenteCarga;
use App\Reportes\Fuentes\FuenteFacturasOracle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FuenteCarga>
 */
class FuenteCargaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo_fuente' => FuenteFacturasOracle::CODIGO,
            'estado' => FuenteCarga::PENDIENTE,
            'archivo' => 'facturas.xlsx',
            'ruta_archivo' => 'fuentes/'.Str::uuid().'.xlsx',
            'cargado_por' => fake()->name(),
            'usuario_id' => '1',
            'fecha_recepcion' => now(),
        ];
    }
}
