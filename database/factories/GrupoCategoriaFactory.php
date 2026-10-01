<?php

namespace Database\Factories;

use App\Models\GrupoCategoria;
use App\Models\Presupuesto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GrupoCategoria>
 */
class GrupoCategoriaFactory extends Factory
{
    protected $model = GrupoCategoria::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'presupuesto_id' => Presupuesto::factory(),
            'nombre' => fake()->unique()->words(2, true),
            'color' => fake()->randomElement([
                '#22c55e', '#8b5cf6', '#3b82f6', '#ec4899',
                '#f59e0b', '#06b6d4', '#ef4444', '#64748b',
            ]),
            'icono' => fake()->randomElement(config('mynab.iconos_grupo_categoria')),
        ];
    }
}
