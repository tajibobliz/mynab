<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Transaccion;
use App\Models\TransaccionSplit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransaccionSplit>
 */
class TransaccionSplitFactory extends Factory
{
    protected $model = TransaccionSplit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaccion_id' => Transaccion::factory()->split(),
            'categoria_id' => Categoria::factory(),
            'monto_centavos' => fake()->numberBetween(500, 20000),
            'notas' => null,
        ];
    }
}
