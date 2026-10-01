<?php

namespace Database\Factories;

use App\Models\Beneficiario;
use App\Models\Presupuesto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Beneficiario>
 */
class BeneficiarioFactory extends Factory
{
    protected $model = Beneficiario::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'presupuesto_id' => Presupuesto::factory(),
            'nombre' => fake()->unique()->company(),
            'notas' => fake()->boolean(50) ? fake()->sentence(10) : null,
        ];
    }
}
