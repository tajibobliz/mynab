<?php

namespace Database\Factories;

use App\Models\Asignacion;
use App\Models\Categoria;
use App\Models\Presupuesto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asignacion>
 */
class AsignacionFactory extends Factory
{
    protected $model = Asignacion::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'presupuesto_id' => Presupuesto::factory(),
            'categoria_id' => Categoria::factory(),
            'año' => now()->year,
            'mes' => now()->month,
            'monto_centavos' => fake()->numberBetween(5000, 200000),
        ];
    }
}
