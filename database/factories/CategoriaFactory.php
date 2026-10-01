<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\GrupoCategoria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Categoria>
 */
class CategoriaFactory extends Factory
{
    protected $model = Categoria::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'grupo_categoria_id' => GrupoCategoria::factory(),
            'nombre' => fake()->unique()->words(2, true),
        ];
    }
}
