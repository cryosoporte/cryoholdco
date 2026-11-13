<?php

namespace Database\Factories;

use App\Models\UsuarioLaboratorio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsuarioLaboratorio>
 */
class UsuarioLaboratorioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'identificacion' => fake()->unique()->numerify('20#########'),
            'password' => 'password',
            'estado' => UsuarioLaboratorio::ESTADO_ACTIVO,
        ];
    }

    /**
     * Un acceso desactivado desde Administración.
     */
    public function inactivo(): static
    {
        return $this->state(fn (array $attributes): array => [
            'estado' => UsuarioLaboratorio::ESTADO_INACTIVO,
        ]);
    }
}
